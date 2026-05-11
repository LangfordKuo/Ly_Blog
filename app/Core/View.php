<?php

namespace LyBlog\Core;

class View
{
    private $templatePath;
    private $cachePath;
    private $data = [];
    private $extensions = [];
    private $globals = [];

    public function __construct(string $templatePath, string $cachePath = null)
    {
        $this->templatePath = rtrim($templatePath, '/');
        $this->cachePath    = $cachePath ? rtrim($cachePath, '/') : STORAGE_DIR . '/cache/views';
        $this->extensions[] = '.twig';
        $this->extensions[] = '.html';
        $this->extensions[] = '.php';
    }

    public function addGlobal(string $name, $value): void
    {
        $this->globals[$name] = $value;
    }

    public function render(string $template, array $data = []): string
    {
        $data  = array_merge($this->globals, $data);
        $file  = $this->findTemplate($template);

        if ($file === null) {
            throw new \RuntimeException("Template not found: {$template}");
        }

        $content   = file_get_contents($file);
        $cacheFile = $this->cachePath . '/' . md5($file) . '_' . md5($content) . '.php';

        if (!file_exists($cacheFile)) {
            $compiled = $this->compile($content);
            $dir = dirname($cacheFile);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            file_put_contents($cacheFile, $compiled, LOCK_EX);
        }

        return $this->evaluate($cacheFile, $data);
    }

    public function display(string $template, array $data = []): void
    {
        echo $this->render($template, $data);
    }

    private function findTemplate(string $template): ?string
    {
        $file = $this->templatePath . '/' . $template;
        if (file_exists($file)) {
            return $file;
        }

        foreach ($this->extensions as $ext) {
            $file = $this->templatePath . '/' . $template . $ext;
            if (file_exists($file)) {
                return $file;
            }
        }
        return null;
    }

    private function evaluate(string $file, array $__data): string
    {
        extract($__data, EXTR_SKIP);
        unset($__data);
        ob_start();
        include $file;
        return ob_get_clean();
    }

    private function compile(string $template): string
    {
        $code = $template;

        // {% extends '...' %}
        if (preg_match('/\{%\s*extends\s+[\'"](.+?)[\'"]\s*%\}/', $code, $extMatch)) {
            $parentFile = $this->findTemplate($extMatch[1]);
            if ($parentFile === null) {
                throw new \RuntimeException("Parent template not found: {$extMatch[1]}");
            }

            // Extract child blocks
            $childBlocks = [];
            preg_match_all('/\{%\s*block\s+(\w+)\s*%\}(.*?)\{%\s*endblock\s*%\}/s', $code, $blockMatches, PREG_SET_ORDER);
            foreach ($blockMatches as $bm) {
                $childBlocks[$bm[1]] = trim($bm[2]);
            }

            // Compile parent
            $code = file_get_contents($parentFile);

            // Replace parent blocks with child blocks
            if (!empty($childBlocks)) {
                $code = preg_replace_callback('/\{%\s*block\s+(\w+)\s*%\}(.*?)\{%\s*endblock\s*%\}/s', function ($m) use ($childBlocks) {
                    $blockName = $m[1];
                    if (isset($childBlocks[$blockName])) {
                        return '{% block ' . $blockName . ' %}' . $childBlocks[$blockName] . '{% endblock %}';
                    }
                    return $m[0];
                }, $code);
            }
        }

        // {% block name %} ... {% endblock %}
        $blocks = [];
        $code = preg_replace_callback('/\{%\s*block\s+(\w+)\s*%\}(.*?)\{%\s*endblock\s*%\}/s', function ($m) use (&$blocks) {
            $blocks[$m[1]] = trim($m[2]);
            return "{{__BLOCK__{$m[1]}__}}";
        }, $code);

        foreach ($blocks as $name => $content) {
            $code = str_replace("{{__BLOCK__{$name}__}}", $content, $code);
        }

        // {% if %} ... {% elseif %} ... {% else %} ... {% endif %}
        $code = preg_replace_callback('/\{%\s*if\s+(.+?)\s*%\}/', function ($m) {
            return '<?php if(' . $this->toPhpCond($m[1]) . '): ?>';
        }, $code);
        $code = preg_replace_callback('/\{%\s*elseif\s+(.+?)\s*%\}/', function ($m) {
            return '<?php elseif(' . $this->toPhpCond($m[1]) . '): ?>';
        }, $code);
        $code = preg_replace('/\{%\s*else\s*%\}/', '<?php else: ?>', $code);
        $code = preg_replace('/\{%\s*endif\s*%\}/', '<?php endif; ?>', $code);

        // {% for item in items %} ... {% endfor %}
        $code = preg_replace_callback('/\{%\s*for\s+(\w+)\s+in\s+(.+?)\s*%\}/', function ($m) {
            return '<?php foreach(' . $this->compileExpr($m[2]) . ' as $' . $m[1] . '): ?>';
        }, $code);
        $code = preg_replace('/\{%\s*endfor\s*%\}/', '<?php endforeach; ?>', $code);

        // {% for key, item in items %}
        $code = preg_replace_callback('/\{%\s*for\s+(\w+)\s*,\s*(\w+)\s+in\s+(.+?)\s*%\}/', function ($m) {
            return '<?php foreach(' . $this->compileExpr($m[3]) . ' as $' . $m[1] . ' => $' . $m[2] . '): ?>';
        }, $code);

        // {% include '...' with {key: val} %}
        $code = preg_replace_callback('/\{%\s*include\s+[\'"](.+?)[\'"](\s+with\s+\{(.+?)\})?\s*%\}/', function ($m) {
            $file = $this->findTemplate($m[1]);
            if ($file === null) {
                return "''";
            }
            $content = file_get_contents($file);
            $compiled = $this->compile($content);

            // Handle with {key: val, key2: val2}
            if (!empty($m[3])) {
                $pairs = explode(',', $m[3]);
                $setVars = '';
                foreach ($pairs as $pair) {
                    $pair = trim($pair);
                    if (strpos($pair, ':') !== false) {
                        list($key, $val) = explode(':', $pair, 2);
                        $key = trim($key);
                        $val = trim($val);
                        $setVars .= "\${$key} = " . $this->compileExpr($val) . "; ";
                    }
                }
                $compiled = '<?php ' . $setVars . ' ?>' . $compiled;
            }

            return $compiled;
        }, $code);

        // {% set var = value %}
        $code = preg_replace('/\{%\s*set\s+(\w+)\s*=\s*(.+?)\s*%\}/', '<?php \$$1 = $2; ?>', $code);

        // {{ var|filter }}
        $code = preg_replace_callback('/\{\{\s*(.+?)\s*\}\}/', function ($m) {
            $expr = trim($m[1]);
            if (strpos($expr, '|') !== false) {
                $parts = explode('|', $expr);
                $var   = trim(array_shift($parts));
                $var   = $this->compileExpr($var);
                foreach ($parts as $filter) {
                    $filter = trim($filter);
                    if ($filter === 'raw') {
                        return '<?php echo ' . $var . '; ?>';
                    } elseif ($filter === 'upper') {
                        $var = 'strtoupper((string)(' . $var . '))';
                    } elseif ($filter === 'lower') {
                        $var = 'strtolower((string)(' . $var . '))';
                    } elseif ($filter === 'escape' || $filter === 'e') {
                        $var = 'htmlspecialchars((string)(' . $var . '), ENT_QUOTES, \'UTF-8\')';
                    } elseif ($filter === 'length') {
                        $var = '(is_array(' . $var . ') ? count(' . $var . ') : mb_strlen((string)(' . $var . ')))';
                    } elseif ($filter === 'nl2br') {
                        $var = 'nl2br((string)(' . $var . '))';
                    } elseif ($filter === 'json') {
                        $var = 'json_encode(' . $var . ', JSON_UNESCAPED_UNICODE)';
                    } elseif ($filter === 'strip_tags') {
                        $var = 'strip_tags((string)(' . $var . '))';
                    } elseif (preg_match('/^date\(([^)]*)\)$/', $filter, $fm)) {
                        $format = $fm[1] ? $fm[1] : "'Y-m-d'";
                        $var = 'date(' . $format . ', is_numeric(' . $var . ') ? ' . $var . ' : strtotime(' . $var . '))';
                    } elseif (preg_match('/^default\(([^)]*)\)$/', $filter, $fm)) {
                        $def = trim($fm[1]);
                        $var = '(' . $var . ' ?: ' . $def . ')';
                    } elseif (preg_match('/^slice\(([^)]*)\)$/', $filter, $fm)) {
                        $args = $fm[1];
                        $var = 'array_slice((array)(' . $var . '), ' . $args . ')';
                    }
                }
                return '<?php echo ' . $var . '; ?>';
            }
            return '<?php echo htmlspecialchars((string)(' . $this->compileExpr($expr) . '), ENT_QUOTES, \'UTF-8\'); ?>';
        }, $code);

        return $code;
    }

    private function compileExpr(string $expr): string
    {
        $expr = trim($expr);

        if ($expr === '') {
            return "''";
        }

        // Already has $ prefix
        if ($expr[0] === '$') {
            return $expr;
        }

        // String literal
        if (($expr[0] === "'" || $expr[0] === '"') && strlen($expr) > 1) {
            return $expr;
        }

        // Numeric literal
        if (is_numeric($expr)) {
            return $expr;
        }

        // Boolean/null
        if (in_array(strtolower($expr), ['true', 'false', 'null'], true)) {
            return $expr;
        }

        // Function call: empty(...), isset(...) etc - do variable replacement inside
        if (preg_match('/^(\w+)\(/', $expr)) {
            $expr = preg_replace_callback('/(?<!\$)\b([a-zA-Z_]\w*)\b/', function ($m) {
                $word = $m[1];
                $skip = ['if', 'else', 'elseif', 'for', 'foreach', 'while', 'do', 'switch', 'case', 'break', 'continue',
                         'return', 'echo', 'print', 'isset', 'empty', 'true', 'false', 'null', 'and', 'or', 'not', 'xor',
                         'new', 'clone', 'throw', 'try', 'catch', 'finally', 'function', 'class',
                         'count', 'strlen', 'strpos', 'substr', 'trim', 'explode', 'implode', 'date', 'time',
                         'is_array', 'is_string', 'is_numeric', 'is_int', 'mb_strlen',
                         'htmlspecialchars', 'strip_tags', 'json_encode', 'json_decode',
                         'array', 'int', 'string', 'bool', 'float', 'double'];
                if (in_array(strtolower($word), $skip, true)) {
                    return $word;
                }
                return '$' . $word;
            }, $expr);
            return $expr;
        }

        // Constant with ::
        if (strpos($expr, '::') !== false) {
            return $expr;
        }

        // Dot notation: replace obj.prop → $obj['prop'] anywhere in expression
        if (preg_match('/^(\w+)$/', $expr)) {
            return '$' . $expr;
        }

        $dotted = preg_replace_callback('/\b(\w+)\.(\w+)\b/', function ($m) {
            return '$' . $m[1] . "['{$m[2]}']";
        }, $expr);

        if ($dotted !== $expr) {
            // If dot notation was applied, also convert remaining standalone words
            $dotted = preg_replace_callback('/(?<!\$)(?<![\x27])\b([a-zA-Z_]\w*)\b/', function ($m) {
                $word = $m[1];
                $skip = ['if', 'else', 'elseif', 'for', 'foreach', 'while', 'do', 'switch', 'case', 'break', 'continue',
                         'return', 'echo', 'print', 'isset', 'empty', 'true', 'false', 'null', 'and', 'or', 'not', 'xor',
                         'new', 'clone', 'throw', 'try', 'catch', 'finally', 'function', 'class',
                         'count', 'strlen', 'strpos', 'substr', 'trim', 'explode', 'implode', 'date', 'time',
                         'is_array', 'is_string', 'is_numeric', 'is_int', 'mb_strlen',
                         'htmlspecialchars', 'strip_tags', 'json_encode', 'json_decode',
                         'array', 'int', 'string', 'bool', 'float', 'double'];
                if (in_array(strtolower($word), $skip, true)) {
                    return $word;
                }
                return '$' . $word;
            }, $dotted);
            return $dotted;
        }

        // Regular variable replacement (skip words already after $)
        $expr = preg_replace_callback('/(?<!\$)\b([a-zA-Z_]\w*)\b/', function ($m) {
            $word = $m[1];
            $skip = ['if', 'else', 'elseif', 'for', 'foreach', 'while', 'do', 'switch', 'case', 'break', 'continue',
                     'return', 'echo', 'print', 'isset', 'empty', 'true', 'false', 'null', 'and', 'or', 'not', 'xor',
                     'new', 'clone', 'throw', 'try', 'catch', 'finally', 'function', 'class',
                     'count', 'strlen', 'strpos', 'substr', 'trim', 'explode', 'implode', 'date', 'time',
                     'is_array', 'is_string', 'is_numeric', 'is_int', 'mb_strlen',
                     'htmlspecialchars', 'strip_tags', 'json_encode', 'json_decode',
                     'array', 'int', 'string', 'bool', 'float', 'double'];
            if (in_array(strtolower($word), $skip, true)) {
                return $word;
            }
            return '$' . $word;
        }, $expr);

        return $expr;
    }

    private function toPhpCond(string $cond): string
    {
        $cond = trim($cond);

        // Pre-process |filter in conditions: var|length → count(var) / mb_strlen(var)
        $cond = preg_replace_callback('/(\w+(?:\.\w+)*)\s*\|\s*length/', function ($m) {
            $var = '$' . $m[1];
            return '(is_array(' . $var . ') ? count(' . $var . ') : mb_strlen((string)(' . $var . ')))';
        }, $cond);

        // var|default(x) → ($var ?: x)
        $cond = preg_replace_callback('/(\w+(?:\.\w+)*)\s*\|\s*default\(([^)]+)\)/', function ($m) {
            $var = '$' . $m[1];
            return '(' . $var . ' ?: ' . trim($m[2]) . ')';
        }, $cond);

        // Handle 'not' operator
        $cond = preg_replace('/\bnot\b/', '!', $cond);

        // Handle 'is defined' test
        $cond = preg_replace('/(\$?\w+(?:\.\w+)*)\s+is\s+defined/', 'isset($1)', $cond);

        // Handle 'is empty' test
        $cond = preg_replace('/(\$?\w+(?:\.\w+)*)\s+is\s+empty/', 'empty($1)', $cond);

        // Convert comparison operators
        $cond = preg_replace('/\band\b/i', '&&', $cond);
        $cond = preg_replace('/\bor\b/i', '||', $cond);

        // Convert remaining identifier references
        $cond = $this->compileExpr($cond);

        return $cond;
    }
}
