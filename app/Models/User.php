<?php

namespace LyBlog\Models;

use LyBlog\Core\Database;

class User extends Model
{
    protected static $table = 'users';
    protected static $primaryKey = 'id';
    protected static $fillable = [
        'username', 'email', 'password', 'display_name',
        'role_id', 'status', 'avatar', 'bio', 'website',
        'last_login', 'last_ip',
    ];

    public static function findByUsername(string $username): ?array
    {
        return static::where('username', $username);
    }

    public static function findByEmail(string $email): ?array
    {
        return static::where('email', $email);
    }

    public static function findByLogin(string $login): ?array
    {
        return static::where('username', $login) ?? static::where('email', $login);
    }

    public static function createUser(array $data): ?int
    {
        if (isset($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);
        }

        if (!isset($data['role_id'])) {
            $data['role_id'] = 5;
        }

        if (!isset($data['display_name'])) {
            $data['display_name'] = $data['username'] ?? '';
        }

        return static::create($data);
    }

    public static function updateUser($id, array $data): int
    {
        if (isset($data['password']) && !empty($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);
        } else {
            unset($data['password']);
        }

        return static::update($id, $data);
    }

    public static function verifyPassword(int $userId, string $password): bool
    {
        $user = static::find($userId);
        if (!$user) return false;

        return password_verify($password, $user['password']);
    }

    public static function attempt(string $username, string $password): ?array
    {
        $user = static::findByUsername($username);
        if (!$user) {
            $user = static::findByEmail($username);
        }

        if (!$user) {
            return null;
        }

        if (!$user['status']) {
            return null;
        }

        if (!password_verify($password, $user['password'])) {
            return null;
        }

        // Rehash if needed
        if (password_needs_rehash($user['password'], PASSWORD_BCRYPT, ['cost' => 12])) {
            $db = Database::getInstance();
            $db->update('users', ['password' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 12])], 'id = ?', [$user['id']]);
        }

        // Update last login
        $db = Database::getInstance();
        $db->update('users', [
            'last_login' => date('Y-m-d H:i:s'),
            'last_ip'    => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
        ], 'id = ?', [$user['id']]);

        return $user;
    }

    public static function getRole(int $userId): ?array
    {
        $user = static::find($userId);
        if (!$user) return null;

        return Role::find($user['role_id']);
    }

    public static function can(int $userId, string $permission): bool
    {
        $role = static::getRole($userId);
        if (!$role) return false;

        return Role::hasPermission($role, $permission);
    }

    public static function isActive(int $userId): bool
    {
        $user = static::find($userId);
        return $user && $user['status'] == 1;
    }
}
