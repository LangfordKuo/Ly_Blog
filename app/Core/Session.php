<?php

namespace LyBlog\Core;

class Session
{
    private static $started = false;

    public static function start(): void
    {
        if (self::$started) {
            return;
        }

        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.cookie_httponly', 1);
            ini_set('session.cookie_samesite', 'Lax');
            ini_set('session.use_strict_mode', 1);
            session_start();
        }

        self::$started = true;

        self::ageFlash();
    }

    public static function get(string $key, $default = null)
    {
        self::start();
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, $value): void
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool
    {
        self::start();
        return isset($_SESSION[$key]);
    }

    public static function remove(string $key): void
    {
        self::start();
        unset($_SESSION[$key]);
    }

    public static function destroy(): void
    {
        self::start();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();
        self::$started = false;
    }

    public static function regenerate(): void
    {
        self::start();
        session_regenerate_id(true);
    }

    public static function flash(string $key, $value = null)
    {
        self::start();

        if ($value !== null) {
            $_SESSION['_flash'][$key] = $value;
            $_SESSION['_flash_new'][$key] = true;
            return null;
        }

        $val = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);
        return $val;
    }

    public static function hasFlash(string $key): bool
    {
        self::start();
        return isset($_SESSION['_flash'][$key]);
    }

    public static function keepFlash(array $keys = []): void
    {
        self::start();

        if (empty($keys)) {
            foreach ($_SESSION['_flash'] ?? [] as $key => $value) {
                $_SESSION['_flash_new'][$key] = true;
            }
        } else {
            foreach ($keys as $key) {
                if (isset($_SESSION['_flash'][$key])) {
                    $_SESSION['_flash_new'][$key] = true;
                }
            }
        }
    }

    private static function ageFlash(): void
    {
        $new = $_SESSION['_flash_new'] ?? [];

        foreach ($_SESSION['_flash'] ?? [] as $key => $value) {
            if (!isset($new[$key])) {
                unset($_SESSION['_flash'][$key]);
            }
        }

        $_SESSION['_flash_new'] = [];
    }

    public static function csrfToken(): string
    {
        self::start();

        if (!isset($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['_csrf_token'];
    }

    public static function csrfField(): string
    {
        return '<input type="hidden" name="_csrf_token" value="' . self::csrfToken() . '">';
    }

    public static function validateCsrf(?string $token = null): bool
    {
        self::start();

        $token = $token ?? $_POST['_csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

        if (empty($_SESSION['_csrf_token']) || empty($token)) {
            return false;
        }

        return hash_equals($_SESSION['_csrf_token'], $token);
    }

    public static function regenerateCsrf(): string
    {
        self::start();
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        return $_SESSION['_csrf_token'];
    }

    public static function login(int $userId, string $username): void
    {
        self::start();
        self::regenerate();
        $_SESSION['user_id']  = $userId;
        $_SESSION['username'] = $username;
        $_SESSION['logged_in_at'] = time();
    }

    public static function logout(): void
    {
        self::start();
        unset($_SESSION['user_id'], $_SESSION['username'], $_SESSION['logged_in_at']);
        self::regenerate();
    }

    public static function isLoggedIn(): bool
    {
        self::start();
        return isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0;
    }

    public static function getUserId(): ?int
    {
        self::start();
        return $_SESSION['user_id'] ?? null;
    }

    public static function getUsername(): ?string
    {
        self::start();
        return $_SESSION['username'] ?? null;
    }
}
