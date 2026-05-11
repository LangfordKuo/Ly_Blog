<?php
/**
 * Phase 3 Tests: Model, User, Role, Auth
 */

require __DIR__ . '/../app/Core/Autoloader.php';
$autoloader = new \LyBlog\Core\Autoloader('LyBlog\\', __DIR__ . '/../app');
$autoloader->register();

define('ROOT_DIR', dirname(__DIR__));
define('STORAGE_DIR', ROOT_DIR . '/storage');
define('THEMES_DIR', ROOT_DIR . '/themes');
define('PLUGINS_DIR', ROOT_DIR . '/plugins');

use LyBlog\Models\User;
use LyBlog\Models\Role;
use LyBlog\Core\Validator;
use LyBlog\Core\Sanitizer;

$pass = 0;
$fail = 0;

function check(string $name, $result, $expected = true) {
    global $pass, $fail;
    if ($expected === true) {
        if ($result) { echo "  PASS: $name\n"; $pass++; }
        else { echo "  FAIL: $name\n"; $fail++; }
    } else {
        if ($result === $expected) { echo "  PASS: $name\n"; $pass++; }
        else { echo "  FAIL: $name (got: " . json_encode($result) . ", expected: " . json_encode($expected) . ")\n"; $fail++; }
    }
}

// ─── 1. Role Permissions ──────────────────
echo "▶ Role Permissions\n";

$superAdmin = ['permissions' => '{"*":true}'];
$admin = ['permissions' => '{"*":true}'];
$editor = ['permissions' => '{"article.create":true,"article.edit":true,"article.publish":true,"comment.moderate":true}'];
$author = ['permissions' => '{"article.create":true,"article.edit_own":true,"article.delete_own":true}'];
$subscriber = ['permissions' => '{"comment.create":true}'];

check('superadmin has *', Role::hasPermission($superAdmin, 'article.create'));
check('superadmin has any', Role::hasPermission($superAdmin, 'anything.here'));
check('admin has any', Role::hasPermission($admin, 'plugin.manage'));

check('editor can create article', Role::hasPermission($editor, 'article.create'));
check('editor can publish', Role::hasPermission($editor, 'article.publish'));
check('editor cannot delete article', !Role::hasPermission($editor, 'article.delete'));
check('editor cannot manage plugins', !Role::hasPermission($editor, 'plugin.manage'));

check('author can create', Role::hasPermission($author, 'article.create'));
check('author can edit_own', Role::hasPermission($author, 'article.edit_own'));
check('author cannot publish', !Role::hasPermission($author, 'article.publish'));

check('subscriber can comment', Role::hasPermission($subscriber, 'comment.create'));
check('subscriber cannot create article', !Role::hasPermission($subscriber, 'article.create'));

// canAccessAdmin
check('superadmin can access admin', Role::canAccessAdmin($superAdmin));
check('admin can access admin', Role::canAccessAdmin($admin));
check('editor cannot access admin', !Role::canAccessAdmin($editor));
check('author cannot access admin', !Role::canAccessAdmin($author));
check('subscriber cannot access admin', !Role::canAccessAdmin($subscriber));

// getAllPermissions
$allPerms = Role::getAllPermissions();
check('getAllPermissions returns array', is_array($allPerms) && count($allPerms) > 5);

// ─── 2. Password Hashing ───────────────────
echo "\n▶ Password Hashing\n";

$password = 'TestPassword123!';
$hash1 = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
$hash2 = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);

check('hash is string', is_string($hash1) && strlen($hash1) >= 60);
check('two hashes differ', $hash1 !== $hash2);
check('verify correct password', password_verify($password, $hash1));
check('verify correct password 2', password_verify($password, $hash2));
check('verify wrong password', !password_verify('WrongPassword', $hash1));

check('needs rehash (cost 10→12)', password_needs_rehash($hash1, PASSWORD_BCRYPT, ['cost' => 12]));

$properHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
check('proper hash verify', password_verify($password, $properHash));
check('proper hash no rehash', !password_needs_rehash($properHash, PASSWORD_BCRYPT, ['cost' => 12]));

// ─── 3. Validation Rules ───────────────────
echo "\n▶ Auth Validation Rules\n";

$v = Validator::quick(
    ['username' => 'admin', 'password' => 'secret123'],
    ['username' => 'required|min:3|max:50', 'password' => 'required|min:6'],
    ['username' => '用户名', 'password' => '密码']
);
check('auth validation pass', $v->passes());

$v2 = Validator::quick(
    ['username' => '', 'password' => 'ab'],
    ['username' => 'required', 'password' => 'required|min:6'],
    ['username' => '用户名', 'password' => '密码']
);
check('auth validation fail empty username', $v2->hasError('username'));
check('auth validation fail short password', $v2->hasError('password'));

// Test slug validation for username
$v3 = Validator::quick(['username' => 'admin_user'], ['username' => 'regex:/^[a-zA-Z0-9_]+$/']);
check('username regex pass', $v3->passes());

$v4 = Validator::quick(['username' => 'admin user!'], ['username' => 'regex:/^[a-zA-Z0-9_]+$/']);
check('username regex fail', $v4->fails());

// ─── 4. Sanitizer for Auth ─────────────────
echo "\n▶ Auth Sanitizer\n";

check('sanitize username', Sanitizer::alphanumeric('John Doe!') === 'JohnDoe');
check('sanitize email', Sanitizer::email(' TEST@Example.com ') === 'TEST@Example.com');
check('sanitize text (XSS)', strpos(Sanitizer::text('<script>alert(1)</script>'), '<script>') === false);

// ─── 5. Auth Data Structures ───────────────
echo "\n▶ Auth Data Structures\n";

// Test the default roles from the installer
$superAdminPerms = ['*' => true];
$editorPerms = ['article.create' => true, 'article.edit' => true, 'article.delete' => true, 'article.publish' => true, 'page.create' => true, 'page.edit' => true, 'page.delete' => true, 'comment.moderate' => true, 'media.upload' => true];
$authorPerms = ['article.create' => true, 'article.edit_own' => true, 'article.delete_own' => true, 'media.upload' => true];
$subscriberPerms = ['comment.create' => true];

$superAdminRole = ['permissions' => json_encode($superAdminPerms)];
$editorRole = ['permissions' => json_encode($editorPerms)];
$authorRole = ['permissions' => json_encode($authorPerms)];
$subscriberRole = ['permissions' => json_encode($subscriberPerms)];

check('editor can article.create', Role::hasPermission($editorRole, 'article.create'));
check('editor can article.edit', Role::hasPermission($editorRole, 'article.edit'));
check('editor can article.delete', Role::hasPermission($editorRole, 'article.delete'));
check('editor can article.publish', Role::hasPermission($editorRole, 'article.publish'));
check('editor can comment.moderate', Role::hasPermission($editorRole, 'comment.moderate'));
check('editor can media.upload', Role::hasPermission($editorRole, 'media.upload'));
check('editor cannot user.create', !Role::hasPermission($editorRole, 'user.create'));
check('editor cannot plugin.*', !Role::hasPermission($editorRole, 'plugin.manage'));

check('author can article.create', Role::hasPermission($authorRole, 'article.create'));
check('author can article.edit_own', Role::hasPermission($authorRole, 'article.edit_own'));
check('author can article.delete_own', Role::hasPermission($authorRole, 'article.delete_own'));
check('author cannot article.publish', !Role::hasPermission($authorRole, 'article.publish'));
check('author cannot article.edit', !Role::hasPermission($authorRole, 'article.edit'));

check('subscriber can comment.create', Role::hasPermission($subscriberRole, 'comment.create'));
check('subscriber cannot anything else', !Role::hasPermission($subscriberRole, 'article.create'));
check('subscriber cannot media.upload', !Role::hasPermission($subscriberRole, 'media.upload'));

echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Results: $pass passed, $fail failed\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━\n";
exit($fail > 0 ? 1 : 0);
