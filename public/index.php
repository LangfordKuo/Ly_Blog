<?php
/**
 * LyBlog — Single Entry Point
 */

define('ROOT_DIR', dirname(__DIR__));

// Check if installed
if (!file_exists(ROOT_DIR . '/config/app.php') || !file_exists(ROOT_DIR . '/config/database.php')) {
    header('Location: ./install.php');
    exit;
}

// Load autoloader
require ROOT_DIR . '/app/Core/Autoloader.php';

$autoloader = new \LyBlog\Core\Autoloader('LyBlog\\', ROOT_DIR . '/app');
$autoloader->register();

// Boot application
$app = new \LyBlog\Core\Application(ROOT_DIR);
$app->boot();

use LyBlog\Core\Request;
use LyBlog\Core\Router;
use LyBlog\Core\Config;
use LyBlog\Core\Session;
use LyBlog\Core\Hook;

Session::start();

// Fire init hook
Hook::doAction('init');

// Create request and router
$request = new Request();
$router  = new Router();
$adminPrefix = Config::get('admin_path', 'admin');
$router->setAdminPrefix($adminPrefix);

// ─── Admin Routes ───────────────────────────────
$router->group('/' . $adminPrefix, function (Router $router) {
    // Auth (no login required)
    $router->get('/login', 'Admin\AuthController@loginForm');
    $router->post('/login', 'Admin\AuthController@login');
    $router->get('/logout', 'Admin\AuthController@logout');

    // Protected
    $router->get('/', 'Admin\DashboardController@index');
    $router->get('/dashboard', 'Admin\DashboardController@index');

    // Articles
    $router->get('/articles', 'Admin\ArticleController@index');
    $router->get('/articles/create', 'Admin\ArticleController@create');
    $router->post('/articles', 'Admin\ArticleController@store');
    $router->get('/articles/{id}/edit', 'Admin\ArticleController@edit');
    $router->post('/articles/{id}/update', 'Admin\ArticleController@update');
    $router->get('/articles/{id}/delete', 'Admin\ArticleController@delete');

    // Pages
    $router->get('/pages', 'Admin\PageController@index');
    $router->get('/pages/create', 'Admin\PageController@create');
    $router->post('/pages', 'Admin\PageController@store');
    $router->get('/pages/{id}/edit', 'Admin\PageController@edit');
    $router->post('/pages/{id}/update', 'Admin\PageController@update');
    $router->get('/pages/{id}/delete', 'Admin\PageController@delete');

    // Comments
    $router->get('/comments', 'Admin\CommentController@index');
    $router->get('/comments/{id}/approve', 'Admin\CommentController@approve');
    $router->get('/comments/{id}/spam', 'Admin\CommentController@spam');
    $router->get('/comments/{id}/delete', 'Admin\CommentController@delete');

    // Categories
    $router->get('/categories', 'Admin\CategoryController@index');
    $router->get('/categories/create', 'Admin\CategoryController@create');
    $router->post('/categories', 'Admin\CategoryController@store');
    $router->get('/categories/{id}/edit', 'Admin\CategoryController@edit');
    $router->post('/categories/{id}/update', 'Admin\CategoryController@update');
    $router->get('/categories/{id}/delete', 'Admin\CategoryController@delete');

    // Tags
    $router->get('/tags', 'Admin\TagController@index');
    $router->get('/tags/create', 'Admin\TagController@create');
    $router->post('/tags', 'Admin\TagController@store');
    $router->get('/tags/{id}/edit', 'Admin\TagController@edit');
    $router->post('/tags/{id}/update', 'Admin\TagController@update');
    $router->get('/tags/{id}/delete', 'Admin\TagController@delete');

    // Users
    $router->get('/users', 'Admin\UserController@index');
    $router->get('/users/create', 'Admin\UserController@create');
    $router->post('/users', 'Admin\UserController@store');
    $router->get('/users/{id}/edit', 'Admin\UserController@edit');
    $router->post('/users/{id}/update', 'Admin\UserController@update');
    $router->get('/users/{id}/delete', 'Admin\UserController@delete');

    // Roles
    $router->get('/roles', 'Admin\RoleController@index');
    $router->get('/roles/create', 'Admin\RoleController@create');
    $router->post('/roles', 'Admin\RoleController@store');
    $router->get('/roles/{id}/edit', 'Admin\RoleController@edit');
    $router->post('/roles/{id}/update', 'Admin\RoleController@update');
    $router->get('/roles/{id}/delete', 'Admin\RoleController@delete');

    // Menus
    $router->get('/menus', 'Admin\MenuController@index');
    $router->get('/menus/create', 'Admin\MenuController@create');
    $router->post('/menus', 'Admin\MenuController@store');
    $router->get('/menus/{id}/delete', 'Admin\MenuController@delete');
    $router->get('/menus/{menuId}/items/create', 'Admin\MenuController@createItem');
    $router->post('/menus/{menuId}/items', 'Admin\MenuController@storeItem');
    $router->get('/menus/{menuId}/items/{itemId}/edit', 'Admin\MenuController@editItem');
    $router->post('/menus/{menuId}/items/{itemId}/update', 'Admin\MenuController@updateItem');
    $router->get('/menus/{menuId}/items/{itemId}/delete', 'Admin\MenuController@deleteItem');

    // Links
    $router->get('/links', 'Admin\LinkController@index');
    $router->get('/links/create', 'Admin\LinkController@create');
    $router->post('/links', 'Admin\LinkController@store');
    $router->get('/links/{id}/edit', 'Admin\LinkController@edit');
    $router->post('/links/{id}/update', 'Admin\LinkController@update');
    $router->get('/links/{id}/delete', 'Admin\LinkController@delete');

    // Media
    $router->get('/media', 'Admin\MediaController@index');
    $router->post('/media/upload', 'Admin\MediaController@upload');
    $router->get('/media/delete', 'Admin\MediaController@delete');

    // Themes
    $router->get('/themes', 'Admin\ThemeController@index');
    $router->get('/themes/activate/{slug}', 'Admin\ThemeController@activate');
    $router->get('/themes/settings/{slug}', 'Admin\ThemeController@settings');
    $router->post('/themes/settings/{slug}/save', 'Admin\ThemeController@saveSettings');

    // Plugins
    $router->get('/plugins', 'Admin\PluginController@index');
    $router->get('/plugins/activate/{slug}', 'Admin\PluginController@activate');
    $router->get('/plugins/deactivate/{slug}', 'Admin\PluginController@deactivate');

    // Settings
    $router->get('/settings', 'Admin\SettingController@index');
    $router->post('/settings', 'Admin\SettingController@update');
    $router->get('/settings/clear-cache', 'Admin\SettingController@clearCache');
    $router->post('/settings/test-mail', 'Admin\SettingController@testMail');

    // Backup
    $router->get('/backup', 'Admin\BackupController@index');
    $router->get('/backup/create', 'Admin\BackupController@create');
    $router->get('/backup/download/{filename}', 'Admin\BackupController@download');
    $router->get('/backup/delete/{filename}', 'Admin\BackupController@delete');
});

// ─── Front Routes ───────────────────────────────
$router->get('/', 'Front\HomeController@index');
$router->get('/login', 'Front\AuthController@loginForm');
$router->post('/login', 'Front\AuthController@login');
$router->get('/register', 'Front\AuthController@registerForm');
$router->post('/register', 'Front\AuthController@register');
$router->get('/logout', 'Front\AuthController@logout');
$router->get('/article/{slug}', 'Front\ArticleController@show');
$router->get('/page/{slug}', 'Front\PageController@show');
$router->post('/comment/{articleId}', 'Front\CommentController@store');
$router->get('/search', 'Front\SearchController@index');
$router->get('/archive', 'Front\ArchiveController@index');
$router->get('/archive/{year}', 'Front\ArchiveController@index');
$router->get('/archive/{year}/{month}', 'Front\ArchiveController@index');
$router->get('/tag/{slug}', 'Front\TagController@show');
$router->get('/category/{slug}', 'Front\CategoryController@show');
$router->post('/like/{articleId}', 'Front\LikeController@toggle');
$router->get('/rss', 'Front\RssController@index');

// ─── User Panel Routes ─────────────────────────
$router->group('/user', function (Router $router) {
    $router->get('/', 'User\DashboardController@index');
    $router->get('/articles', 'User\ArticleController@index');
    $router->get('/articles/create', 'User\ArticleController@create');
    $router->post('/articles', 'User\ArticleController@store');
    $router->get('/articles/{id}/edit', 'User\ArticleController@edit');
    $router->post('/articles/{id}/update', 'User\ArticleController@update');
    $router->get('/articles/{id}/delete', 'User\ArticleController@delete');
    $router->get('/profile', 'User\ProfileController@index');
    $router->post('/profile', 'User\ProfileController@update');
    $router->post('/profile/password', 'User\ProfileController@updatePassword');
    $router->get('/media', 'User\MediaController@index');
    $router->post('/media/upload', 'User\MediaController@upload');
});

// ─── Dispatch ──────────────────────────────────
Hook::doAction('before_dispatch', $request);
$router->dispatch($request);
Hook::doAction('after_dispatch', $request);
