<?php

namespace LyBlog\Core;

use LyBlog\Models\User;
use LyBlog\Models\Role;

class Auth
{
    public static function attempt(string $username, string $password): ?array
    {
        // Rate limit check
        if (self::isRateLimited()) {
            return null;
        }

        $user = User::attempt($username, $password);

        if ($user) {
            // Record successful login
            self::logAttempt($username, true);
            Session::login($user['id'], $user['username']);
            return $user;
        }

        // Record failed login
        self::logAttempt($username, false);
        return null;
    }

    public static function logout(): void
    {
        Session::logout();
    }

    public static function user(): ?array
    {
        $userId = Session::getUserId();
        if (!$userId) return null;
        return User::find($userId);
    }

    public static function id(): ?int
    {
        return Session::getUserId();
    }

    public static function check(): bool
    {
        return Session::isLoggedIn();
    }

    public static function guest(): bool
    {
        return !self::check();
    }

    public static function can(string $permission): bool
    {
        $userId = self::id();
        if (!$userId) return false;
        return User::can($userId, $permission);
    }

    public static function hasRole(string $slug): bool
    {
        $user = self::user();
        if (!$user) return false;

        $role = Role::find($user['role_id']);
        return $role && $role['slug'] === $slug;
    }

    public static function canAccessAdmin(): bool
    {
        $user = self::user();
        if (!$user) return false;

        return Role::canAccessAdminById($user['role_id']);
    }

    public static function requireLogin(): void
    {
        if (self::guest()) {
            $siteUrl = Config::get('site_url', '/');
            $adminPath = Config::get('admin_path', 'admin');
            $redirectUrl = $siteUrl . '/' . $adminPath . '/login';
            header('Location: ' . $redirectUrl);
            exit;
        }
    }

    public static function requirePermission(string $permission): void
    {
        self::requireLogin();

        if (!self::can($permission)) {
            http_response_code(403);
            echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>403</title></head>';
            echo '<body style="font-family:sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;background:#f5f5f7;"><div style="text-align:center;"><h1 style="font-size:72px;font-weight:200;margin:0;">403</h1><p>权限不足</p></div></body></html>';
            exit;
        }
    }

    public static function isRateLimited(): bool
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $maxAttempts = 5;
        $timeWindow = 900; // 15 minutes

        $db = Database::getInstance();
        if ($db === null) return false;

        $cutoff = date('Y-m-d H:i:s', time() - $timeWindow);
        $count = $db->count('login_log', "ip = ? AND success = 0 AND created_at > ?", [$ip, $cutoff]);

        return $count >= $maxAttempts;
    }

    public static function getRateLimitRemaining(): int
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $maxAttempts = 5;
        $timeWindow = 900;

        $db = Database::getInstance();
        if ($db === null) return $maxAttempts;

        $cutoff = date('Y-m-d H:i:s', time() - $timeWindow);
        $count = $db->count('login_log', "ip = ? AND success = 0 AND created_at > ?", [$ip, $cutoff]);

        return max(0, $maxAttempts - $count);
    }

    private static function logAttempt(string $username, bool $success): void
    {
        $db = Database::getInstance();
        if ($db === null) return;

        $db->insert('login_log', [
            'ip'       => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            'username' => $username,
            'success'  => $success ? 1 : 0,
        ]);
    }
}
