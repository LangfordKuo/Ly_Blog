<?php

namespace LyBlog\Core;

class Application
{
    private static $instance = null;
    private $config         = [];
    private $basePath;

    public function __construct(string $basePath)
    {
        $this->basePath = $basePath;
        self::$instance = $this;
    }

    public static function getInstance(): ?self
    {
        return self::$instance;
    }

    public function getBasePath(): string
    {
        return $this->basePath;
    }

    public function boot(): void
    {
        if (!defined('ROOT_DIR')) {
            define('ROOT_DIR', $this->basePath);
        }

        if (!defined('PUBLIC_DIR')) {
            define('PUBLIC_DIR', $this->basePath . '/public');
        }

        if (!defined('STORAGE_DIR')) {
            define('STORAGE_DIR', $this->basePath . '/storage');
        }

        if (!defined('THEMES_DIR')) {
            define('THEMES_DIR', $this->basePath . '/themes');
        }

        if (!defined('PLUGINS_DIR')) {
            define('PLUGINS_DIR', $this->basePath . '/plugins');
        }

        Config::load();

        $dbConfig = [
            'host'    => Config::get('host'),
            'port'    => Config::get('port', 3306),
            'dbname'  => Config::get('dbname'),
            'user'    => Config::get('user'),
            'pass'    => Config::get('pass'),
            'prefix'  => Config::get('prefix', ''),
            'charset' => Config::get('charset', 'utf8mb4'),
        ];

        try {
            Database::getInstance($dbConfig);
        } catch (\PDOException $e) {
            $this->renderDbError($e->getMessage());
            exit;
        }

        date_default_timezone_set(Config::get('timezone', 'Asia/Shanghai'));

        $this->initSession();

        $this->initLogger();

        $this->loadPlugins();

        $this->setSecurityHeaders();

        $this->checkMaintenance();

        $this->runScheduledTasks();
    }

    private function runScheduledTasks(): void
    {
        // Rate limit: run at most once per minute
        $lastRun = Session::get('_cron_last', 0);
        if (time() - $lastRun < 60) return;
        Session::set('_cron_last', time());

        try {
            $db = Database::getInstance();
            if (!$db) return;

            // Auto-publish scheduled articles
            $db->query(
                "UPDATE {articles} SET status = 'published', updated_at = NOW() 
                 WHERE status = 'draft' AND published_at IS NOT NULL AND published_at <= NOW()"
            );
        } catch (\Exception $e) {}
    }

    private function checkMaintenance(): void
    {
        if (Config::get('maintenance_mode') != '1') return;
        if (Session::isLoggedIn() && \LyBlog\Core\Auth::canAccessAdmin()) return;

        if (php_sapi_name() === 'cli') return;

        http_response_code(503);
        header('Retry-After: 3600');
        $msg = Config::get('maintenance_message', '网站维护中，请稍后再来...');
        $siteName = Config::get('site_name', 'LyBlog');
        echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><title>维护中 - ' . htmlspecialchars($siteName) . '</title>';
        echo '<style>*{margin:0;padding:0;box-sizing:border-box}body{font-family:-apple-system,sans-serif;background:#f5f5f7;display:flex;align-items:center;justify-content:center;min-height:100vh;text-align:center}.card{background:rgba(255,255,255,0.72);backdrop-filter:blur(20px);border:1px solid rgba(0,0,0,0.06);border-radius:16px;padding:48px;max-width:480px}h1{font-size:24px;font-weight:500;margin-bottom:12px}p{color:#6e6e73;line-height:1.6}</style>';
        echo '</head><body><div class="card"><h1>🚧 ' . htmlspecialchars($siteName) . '</h1><p>' . htmlspecialchars($msg) . '</p></div></body></html>';
        exit;
    }

    private function setSecurityHeaders(): void
    {
        if (headers_sent()) return;

        $headers = [
            'X-Content-Type-Options'  => 'nosniff',
            'X-Frame-Options'         => 'SAMEORIGIN',
            'X-XSS-Protection'        => '1; mode=block',
            'Referrer-Policy'         => 'strict-origin-when-cross-origin',
            'X-Powered-By'            => null,
        ];

        // HTTPS-only: HSTS
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        // CSP: basic policy
        $siteUrl = Config::get('site_url', '');
        $csp = "default-src 'self'; "
             . "script-src 'self' 'unsafe-inline'; "
             . "style-src 'self' 'unsafe-inline'; "
             . "img-src 'self' data: https:; "
             . "font-src 'self' data:; "
             . "connect-src 'self'; "
             . "media-src 'self'; "
             . "frame-src 'self'; "
             . "frame-ancestors 'self'; "
             . "form-action 'self'; "
             . "base-uri 'self';";

        $headers['Content-Security-Policy'] = $csp;

        // Permissions-Policy: restrict unnecessary browser features
        $headers['Permissions-Policy'] = 'camera=(), microphone=(), geolocation=(), payment=()';

        // Allow plugins to modify headers
        $headers = Hook::applyFilters('security_headers', $headers);

        // Remove headers set to null
        foreach ($headers as $key => $value) {
            if ($value === null) {
                header_remove($key);
            } elseif (is_string($value)) {
                header("{$key}: {$value}");
            }
        }
    }

    private function initSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.cookie_httponly', 1);
            ini_set('session.cookie_samesite', 'Lax');
            ini_set('session.use_strict_mode', 1);
            session_start();
        }
    }

    private function initLogger(): void
    {
        $logLevel = Config::get('log_level', 'error');
        $logLevels = ['debug' => 4, 'info' => 3, 'warning' => 2, 'error' => 1, 'none' => 0];
        $currentLevel = $logLevels[strtolower($logLevel)] ?? 1;

        set_exception_handler(function (\Throwable $e) use ($currentLevel, $logLevels) {
            if ($currentLevel >= 1) {
                $logFile = STORAGE_DIR . '/logs/' . date('Y-m-d') . '.log';
                $dir = dirname($logFile);
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
                $message = sprintf(
                    "[%s] %s: %s in %s:%d\nStack trace:\n%s\n",
                    date('Y-m-d H:i:s'),
                    get_class($e),
                    $e->getMessage(),
                    $e->getFile(),
                    $e->getLine(),
                    $e->getTraceAsString()
                );
                file_put_contents($logFile, $message, FILE_APPEND | LOCK_EX);
            }

            if (Config::get('debug', false)) {
                echo '<h1>Exception</h1>';
                echo '<p><strong>' . get_class($e) . '</strong>: ' . $e->getMessage() . '</p>';
                echo '<p>File: ' . $e->getFile() . ':' . $e->getLine() . '</p>';
                echo '<pre>' . $e->getTraceAsString() . '</pre>';
            } else {
                http_response_code(500);
                echo '<h1>500 Internal Server Error</h1>';
            }
        });
    }

    private function loadPlugins(): void
    {
        Plugin::loadAll();
    }

    private function renderDbError(string $message): void
    {
        $siteName = Config::get('site_name', 'LyBlog');
        http_response_code(500);
        echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>数据库连接失败 - ' . htmlspecialchars($siteName) . '</title>';
        echo '<style>
            *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
            body{font-family:-apple-system,BlinkMacSystemFont,"PingFang SC","Microsoft YaHei",sans-serif;background:#f5f5f7;color:#1d1d1f;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px}
            .card{background:rgba(255,255,255,0.72);backdrop-filter:blur(20px);border:1px solid rgba(0,0,0,0.06);border-radius:16px;box-shadow:0 8px 32px rgba(0,0,0,0.06);padding:40px;max-width:560px;width:100%;text-align:center}
            h1{font-size:24px;font-weight:500;margin-bottom:12px}
            p{color:#6e6e73;margin-bottom:16px;line-height:1.6;font-size:14px}
            .error{background:rgba(255,59,48,0.06);color:#ff3b30;padding:12px 16px;border-radius:8px;font-size:13px;margin-bottom:20px;word-break:break-all;font-family:monospace}
            a{color:#1d1d1f;font-size:14px}
        </style>';
        echo '</head><body><div class="card">';
        echo '<h1>⚠ 数据库连接失败</h1>';
        echo '<p>无法连接到 MySQL 数据库，请检查 <code>config/database.php</code> 配置是否正确。</p>';
        echo '<div class="error">' . htmlspecialchars($message) . '</div>';
        echo '<a href="install.php">重新安装 →</a>';
        echo '</div></body></html>';
    }
}
