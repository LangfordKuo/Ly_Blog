<?php

namespace LyBlog\Core;

class Autoloader
{
    private $prefix;
    private $baseDir;

    public function __construct(string $prefix, string $baseDir)
    {
        $this->prefix   = $prefix;
        $this->baseDir  = rtrim($baseDir, '/') . '/';
    }

    public function register(): void
    {
        spl_autoload_register([$this, 'loadClass']);
    }

    public function loadClass(string $class): bool
    {
        $len = strlen($this->prefix);
        if (strncmp($this->prefix, $class, $len) !== 0) {
            return false;
        }

        $relativeClass = substr($class, $len);
        $file = $this->baseDir . str_replace('\\', '/', $relativeClass) . '.php';

        if (file_exists($file)) {
            require $file;
            return true;
        }

        return false;
    }
}
