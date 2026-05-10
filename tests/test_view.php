<?php
require __DIR__ . '/../app/Core/Autoloader.php';
$autoloader = new \LyBlog\Core\Autoloader('LyBlog\\', __DIR__ . '/../app');
$autoloader->register();
define('STORAGE_DIR', __DIR__ . '/../storage');

$tplDir = STORAGE_DIR . '/cache/tpl_test';
@mkdir($tplDir, 0755, true);
array_map('unlink', glob($tplDir . '/*.twig') ?: []);

$view = new \LyBlog\Core\View($tplDir);
$ok = 0;
$err = 0;

function test($view, $tplDir, $name, $template, $data, $expected) {
    global $ok, $err;
    file_put_contents($tplDir . '/test.twig', $template);
    $result = $view->render('test', $data);
    if (trim($result) === trim($expected)) {
        echo "PASS - $name\n";
        $ok++;
    } else {
        echo "FAIL - $name: got [" . trim($result) . "] expected [" . trim($expected) . "]\n";
        $err++;
    }
}

test($view, $tplDir, 'basic', '<h1>{{ name }}</h1>', ['name' => 'World'], '<h1>World</h1>');
test($view, $tplDir, 'if/true', '{% if show %}Y{% else %}N{% endif %}', ['show' => true], 'Y');
test($view, $tplDir, 'if/false', '{% if show %}Y{% else %}N{% endif %}', ['show' => false], 'N');
test($view, $tplDir, 'for', '{% for x in list %}{{ x }},{% endfor %}', ['list' => ['a','b','c']], 'a,b,c,');
test($view, $tplDir, 'upper', '{{ text|upper }}', ['text' => 'hi'], 'HI');
test($view, $tplDir, 'escape', '{{ html|escape }}', ['html' => '<x>'], '&lt;x&gt;');
test($view, $tplDir, 'length', '{{ arr|length }}', ['arr' => [1,2,3,4]], '4');
test($view, $tplDir, 'dot', '{{ user.name }}', ['user' => ['name' => 'John']], 'John');
test($view, $tplDir, 'nl2br', '{{ text|nl2br }}', ['text' => "a\nb"], "a<br />\nb");
test($view, $tplDir, 'json', '{{ data|json }}', ['data' => ['a' => 1]], '{"a":1}');
test($view, $tplDir, 'default', '{{ name|default("Guest") }}', ['name' => null], 'Guest');
test($view, $tplDir, 'strip_tags', '{{ html|strip_tags }}', ['html' => '<p>Hi</p>'], 'Hi');

// extends
file_put_contents($tplDir . '/layout.twig', '<div>{% block body %}X{% endblock %}</div>');
test($view, $tplDir, 'extends', '{% extends "layout.twig" %}{% block body %}OK{% endblock %}', [], '<div>OK</div>');

// include
file_put_contents($tplDir . '/inc.twig', '<span>{{ msg }}</span>');
test($view, $tplDir, 'include', '{% include "inc.twig" %}!', ['msg' => 'Hi'], '<span>Hi</span>!');

// Cleanup
$cleanup = glob($tplDir . '/*.twig') ?: [];
foreach ($cleanup as $f) @unlink($f);

echo "\nResults: $ok passed, $err failed\n";
