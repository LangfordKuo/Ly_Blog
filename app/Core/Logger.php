<?php

namespace LyBlog\Core;

use RuntimeException;

class Logger
{
    const DEBUG   = 100;
    const INFO    = 200;
    const WARNING = 300;
    const ERROR   = 400;

    private static $instance = null;
    private $logDir;
    private $level;
    private $levels = [
        'debug'   => self::DEBUG,
        'info'    => self::INFO,
        'warning' => self::WARNING,
        'error'   => self::ERROR,
        'none'    => 500,
    ];

    private function __construct(string $logDir, string $level = 'error')
    {
        $this->logDir = rtrim($logDir, '/');
        $this->level  = $this->levels[strtolower($level)] ?? self::ERROR;

        if (!is_dir($this->logDir)) {
            @mkdir($this->logDir, 0755, true);
        }

        if (!is_writable($this->logDir)) {
            throw new RuntimeException("Log directory is not writable: {$this->logDir}");
        }
    }

    public static function init(string $logDir = null, string $level = 'error'): self
    {
        if (self::$instance === null) {
            $dir = $logDir ?? STORAGE_DIR . '/logs';
            self::$instance = new self($dir, $level);
        }
        return self::$instance;
    }

    public static function getInstance(): ?self
    {
        return self::$instance;
    }

    public function setLevel(string $level): void
    {
        $this->level = $this->levels[strtolower($level)] ?? self::ERROR;
    }

    public function debug(string $message, array $context = []): void
    {
        $this->log(self::DEBUG, $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->log(self::INFO, $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log(self::WARNING, $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log(self::ERROR, $message, $context);
    }

    public function log(int $level, string $message, array $context = []): void
    {
        if ($level < $this->level) {
            return;
        }

        $levelName = array_flip($this->levels)[$level] ?? 'unknown';
        $levelName = strtoupper($levelName);

        $interpolated = $this->interpolate($message, $context);

        $line = sprintf(
            "[%s] %s.%s: %s%s\n",
            date('Y-m-d H:i:s'),
            $levelName,
            str_pad('', 8 - strlen($levelName)),
            $interpolated,
            !empty($context) ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE) : ''
        );

        $file = $this->logDir . '/' . date('Y-m-d') . '.log';
        file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }

    private function interpolate(string $message, array $context): string
    {
        $replace = [];
        foreach ($context as $key => $val) {
            if (is_scalar($val) || (is_object($val) && method_exists($val, '__toString'))) {
                $replace['{' . $key . '}'] = (string) $val;
            }
        }
        return strtr($message, $replace);
    }

    public function clear(): void
    {
        $files = glob($this->logDir . '/*.log');
        foreach ($files as $file) {
            @unlink($file);
        }
    }

    public function getLogs(int $days = 7): array
    {
        $logs = [];
        $cutoff = strtotime("-{$days} days");

        $files = glob($this->logDir . '/*.log');
        rsort($files);

        foreach ($files as $file) {
            $date = basename($file, '.log');
            $timestamp = strtotime($date);
            if ($timestamp >= $cutoff) {
                $logs[] = [
                    'date'    => $date,
                    'file'    => $file,
                    'size'    => filesize($file),
                    'content' => file_get_contents($file),
                ];
            }
        }

        return $logs;
    }

    public function prune(int $days = 30): int
    {
        $count = 0;
        $cutoff = strtotime("-{$days} days");

        $files = glob($this->logDir . '/*.log');
        foreach ($files as $file) {
            $date = basename($file, '.log');
            if (strtotime($date) < $cutoff) {
                @unlink($file);
                $count++;
            }
        }

        return $count;
    }
}
