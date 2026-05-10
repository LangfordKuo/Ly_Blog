<?php

namespace LyBlog\Models;

class Role extends Model
{
    protected static $table = 'roles';
    protected static $primaryKey = 'id';
    protected static $fillable = ['name', 'slug', 'description', 'permissions', 'is_system'];

    public static function findBySlug(string $slug): ?array
    {
        return static::where('slug', $slug);
    }

    public static function getPermissions(int $roleId): array
    {
        $role = static::find($roleId);
        if (!$role) return [];

        return static::decodePermissions($role['permissions']);
    }

    public static function setPermissions(int $roleId, array $permissions): int
    {
        return static::update($roleId, [
            'permissions' => json_encode($permissions, JSON_UNESCAPED_UNICODE),
        ]);
    }

    public static function hasPermission($role, string $permission): bool
    {
        if (is_numeric($role)) {
            $role = static::find($role);
        }

        if (!$role) return false;

        $perms = static::decodePermissions($role['permissions'] ?? '');

        // Super admin has all permissions
        if (isset($perms['*']) && $perms['*'] === true) {
            return true;
        }

        // Check wildcard
        $parts = explode('.', $permission);
        $check = '';
        foreach ($parts as $part) {
            $check .= ($check ? '.' : '') . $part;
            if (isset($perms[$check . '.*']) && $perms[$check . '.*'] === true) {
                return true;
            }
        }

        return isset($perms[$permission]) && $perms[$permission] === true;
    }

    public static function canAccessAdmin($role): bool
    {
        if (is_numeric($role)) {
            $role = static::find($role);
        }

        if (!$role) return false;

        $perms = static::decodePermissions($role['permissions'] ?? '');

        if (isset($perms['*']) && $perms['*'] === true) {
            return true;
        }

        $adminPerms = ['article.', 'page.', 'user.', 'setting.', 'theme.', 'plugin.', 'media.', 'category.', 'tag.', 'link.', 'backup.', 'dashboard'];
        foreach ($adminPerms as $prefix) {
            foreach ($perms as $key => $val) {
                if ($val === true && strpos($key, $prefix) === 0) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function canAccessAdminById(int $roleId): bool
    {
        $role = static::find($roleId);
        return static::canAccessAdmin($role);
    }

    public static function getSystemRoles(): array
    {
        return static::getBy('is_system', 1);
    }

    public static function getAssignableRoles(): array
    {
        return static::all('id ASC');
    }

    private static function decodePermissions($permissions): array
    {
        if (empty($permissions)) return [];
        $decoded = json_decode($permissions, true);
        return is_array($decoded) ? $decoded : [];
    }

    public static function getAllPermissions(): array
    {
        return [
            'dashboard'    => '访问仪表盘',
            'article.*'    => '文章管理(全部)',
            'article.create'  => '创建文章',
            'article.edit'    => '编辑文章',
            'article.edit_own' => '编辑自己的文章',
            'article.delete'  => '删除文章',
            'article.delete_own' => '删除自己的文章',
            'article.publish' => '发布文章',
            'page.*'       => '页面管理(全部)',
            'page.create'  => '创建页面',
            'page.edit'    => '编辑页面',
            'page.delete'  => '删除页面',
            'comment.*'    => '评论管理(全部)',
            'comment.moderate' => '审核评论',
            'comment.delete' => '删除评论',
            'user.*'       => '用户管理(全部)',
            'user.create'  => '创建用户',
            'user.edit'    => '编辑用户',
            'user.delete'  => '删除用户',
            'category.*'   => '分类管理(全部)',
            'tag.*'        => '标签管理(全部)',
            'media.*'      => '媒体管理(全部)',
            'media.upload' => '上传文件',
            'setting.*'    => '设置管理(全部)',
            'theme.*'      => '主题管理(全部)',
            'plugin.*'     => '插件管理(全部)',
            'link.*'       => '友情链接管理(全部)',
            'backup.*'     => '备份管理(全部)',
        ];
    }
}
