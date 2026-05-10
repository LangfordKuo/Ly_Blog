<?php
/**
 * LyBlog Installation Wizard
 * Self-contained installer — does not depend on framework classes.
 */

session_start();

$rootDir = dirname(__DIR__);
$step    = isset($_GET['step']) ? (int) $_GET['step'] : 1;
$error   = '';
$success = '';

if (file_exists($rootDir . '/config/app.php') && file_exists($rootDir . '/config/database.php')) {
    $step = 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'install') {
        try {
            $dbConfig = [
                'host'    => $_POST['db_host'],
                'port'    => $_POST['db_port'] ?: 3306,
                'dbname'  => $_POST['db_name'],
                'user'    => $_POST['db_user'],
                'pass'    => $_POST['db_pass'],
                'prefix'  => $_POST['db_prefix'] ?: 'lyblog_',
                'charset' => 'utf8mb4',
            ];

            $appConfig = [
                'site_name'   => $_POST['site_name'],
                'site_url'    => rtrim($_POST['site_url'], '/'),
                'admin_path'  => trim($_POST['admin_path'], '/') ?: 'admin',
                'debug'       => false,
                'timezone'    => 'Asia/Shanghai',
                'log_level'   => 'error',
                'cache_driver' => 'file',
                'registration' => false,
                'theme'        => 'Default',
            ];

            $adminUser = $_POST['admin_user'];
            $adminPass = $_POST['admin_pass'];
            $adminEmail = $_POST['admin_email'];

            // Test connection
            $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};charset=utf8mb4";
            $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);

            // Create database if not exists
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbConfig['dbname']}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `{$dbConfig['dbname']}`");

            // Create tables
            $prefix = $dbConfig['prefix'];

            $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS `{$prefix}users` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `username` VARCHAR(50) NOT NULL,
                `email` VARCHAR(100) NOT NULL,
                `password` VARCHAR(255) NOT NULL,
                `display_name` VARCHAR(100) DEFAULT NULL,
                `role_id` INT UNSIGNED NOT NULL DEFAULT 2,
                `status` TINYINT(1) NOT NULL DEFAULT 1,
                `avatar` VARCHAR(255) DEFAULT NULL,
                `bio` TEXT DEFAULT NULL,
                `website` VARCHAR(255) DEFAULT NULL,
                `last_login` DATETIME DEFAULT NULL,
                `last_ip` VARCHAR(45) DEFAULT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `username` (`username`),
                UNIQUE KEY `email` (`email`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS `{$prefix}roles` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(50) NOT NULL,
                `slug` VARCHAR(50) NOT NULL,
                `description` VARCHAR(255) DEFAULT NULL,
                `permissions` TEXT DEFAULT NULL,
                `is_system` TINYINT(1) NOT NULL DEFAULT 0,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `slug` (`slug`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS `{$prefix}settings` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `key` VARCHAR(100) NOT NULL,
                `value` TEXT DEFAULT NULL,
                UNIQUE KEY `key` (`key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS `{$prefix}categories` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(100) NOT NULL,
                `slug` VARCHAR(100) NOT NULL,
                `description` TEXT DEFAULT NULL,
                `parent_id` INT UNSIGNED DEFAULT NULL,
                `sort_order` INT DEFAULT 0,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY `slug` (`slug`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS `{$prefix}tags` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(100) NOT NULL,
                `slug` VARCHAR(100) NOT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY `slug` (`slug`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS `{$prefix}articles` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `title` VARCHAR(255) NOT NULL,
                `slug` VARCHAR(255) NOT NULL,
                `content` LONGTEXT DEFAULT NULL,
                `excerpt` TEXT DEFAULT NULL,
                `cover_image` VARCHAR(255) DEFAULT NULL,
                `status` VARCHAR(20) NOT NULL DEFAULT 'draft',
                `author_id` INT UNSIGNED NOT NULL,
                `category_id` INT UNSIGNED DEFAULT NULL,
                `views` INT UNSIGNED NOT NULL DEFAULT 0,
                `likes` INT UNSIGNED NOT NULL DEFAULT 0,
                `password` VARCHAR(255) DEFAULT NULL,
                `published_at` DATETIME DEFAULT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `status` (`status`),
                INDEX `author_id` (`author_id`),
                INDEX `category_id` (`category_id`),
                INDEX `published_at` (`published_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS `{$prefix}article_tag` (
                `article_id` INT UNSIGNED NOT NULL,
                `tag_id` INT UNSIGNED NOT NULL,
                PRIMARY KEY (`article_id`, `tag_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS `{$prefix}article_meta` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `article_id` INT UNSIGNED NOT NULL,
                `meta_key` VARCHAR(100) NOT NULL,
                `meta_value` LONGTEXT DEFAULT NULL,
                INDEX `article_id` (`article_id`),
                UNIQUE KEY `article_meta` (`article_id`, `meta_key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS `{$prefix}comments` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `article_id` INT UNSIGNED NOT NULL,
                `parent_id` INT UNSIGNED DEFAULT NULL,
                `user_id` INT UNSIGNED DEFAULT NULL,
                `author_name` VARCHAR(100) NOT NULL,
                `author_email` VARCHAR(100) DEFAULT NULL,
                `author_url` VARCHAR(255) DEFAULT NULL,
                `content` TEXT NOT NULL,
                `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
                `ip` VARCHAR(45) DEFAULT NULL,
                `user_agent` VARCHAR(255) DEFAULT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX `article_id` (`article_id`),
                INDEX `parent_id` (`parent_id`),
                INDEX `status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS `{$prefix}pages` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `title` VARCHAR(255) NOT NULL,
                `slug` VARCHAR(255) NOT NULL,
                `content` LONGTEXT DEFAULT NULL,
                `status` VARCHAR(20) NOT NULL DEFAULT 'draft',
                `author_id` INT UNSIGNED NOT NULL,
                `template` VARCHAR(50) DEFAULT NULL,
                `sort_order` INT DEFAULT 0,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `slug` (`slug`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS `{$prefix}menus` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(100) NOT NULL,
                `slug` VARCHAR(100) NOT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY `slug` (`slug`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS `{$prefix}menu_items` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `menu_id` INT UNSIGNED NOT NULL,
                `parent_id` INT UNSIGNED DEFAULT NULL,
                `title` VARCHAR(100) NOT NULL,
                `url` VARCHAR(255) DEFAULT NULL,
                `type` VARCHAR(20) NOT NULL DEFAULT 'custom',
                `target_id` INT UNSIGNED DEFAULT NULL,
                `sort_order` INT DEFAULT 0,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX `menu_id` (`menu_id`),
                INDEX `parent_id` (`parent_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS `{$prefix}links` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(100) NOT NULL,
                `url` VARCHAR(255) NOT NULL,
                `description` VARCHAR(255) DEFAULT NULL,
                `logo` VARCHAR(255) DEFAULT NULL,
                `sort_order` INT DEFAULT 0,
                `status` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS `{$prefix}likes` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `article_id` INT UNSIGNED NOT NULL,
                `user_id` INT UNSIGNED DEFAULT NULL,
                `ip` VARCHAR(45) NOT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY `article_ip` (`article_id`, `ip`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS `{$prefix}plugins` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(100) NOT NULL,
                `slug` VARCHAR(100) NOT NULL,
                `version` VARCHAR(20) NOT NULL,
                `status` TINYINT(1) NOT NULL DEFAULT 1,
                `config` TEXT DEFAULT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY `slug` (`slug`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS `{$prefix}login_log` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `ip` VARCHAR(45) NOT NULL,
                `username` VARCHAR(50) DEFAULT NULL,
                `success` TINYINT(1) NOT NULL DEFAULT 0,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX `ip` (`ip`),
                INDEX `created_at` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            // Insert default roles
            $stmt = $pdo->prepare("INSERT INTO `{$prefix}roles` (`name`, `slug`, `description`, `permissions`, `is_system`) VALUES 
                (?, 'super_admin', ?, ?, 1),
                (?, 'admin', ?, ?, 1),
                (?, 'editor', ?, ?, 1),
                (?, 'author', ?, ?, 1),
                (?, 'subscriber', ?, ?, 1)");

            $superAdminPerms = json_encode(['*' => true]);
            $adminPerms = json_encode(['*' => true]);
            $editorPerms = json_encode([
                'article.create' => true, 'article.edit' => true, 'article.delete' => true, 'article.publish' => true,
                'page.create' => true, 'page.edit' => true, 'page.delete' => true,
                'comment.moderate' => true, 'media.upload' => true,
            ]);
            $authorPerms = json_encode([
                'article.create' => true, 'article.edit_own' => true, 'article.delete_own' => true,
                'media.upload' => true,
            ]);
            $subscriberPerms = json_encode(['comment.create' => true]);

            $stmt->execute([
                '超级管理员', '拥有所有权限', $superAdminPerms,
                '管理员', '拥有所有权限', $adminPerms,
                '编辑', '可管理内容和评论', $editorPerms,
                '作者', '可发布和管理自己的文章', $authorPerms,
                '订阅者', '可发表评论', $subscriberPerms,
            ]);

            // Insert admin user
            $hashedPassword = password_hash($adminPass, PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt = $pdo->prepare("INSERT INTO `{$prefix}users` (`username`, `email`, `password`, `display_name`, `role_id`, `status`) VALUES (?, ?, ?, ?, 1, 1)");
            $stmt->execute([$adminUser, $adminEmail, $hashedPassword, $adminUser]);

            // Insert default settings
            foreach ($appConfig as $key => $value) {
                if ($key === 'site_url') {
                    continue;
                }
                $stmt = $pdo->prepare("INSERT INTO `{$prefix}settings` (`key`, `value`) VALUES (?, ?)");
                $stmt->execute([$key, $value]);
            }

            // Insert default category
            $pdo->exec("INSERT INTO `{$prefix}categories` (`name`, `slug`, `description`) VALUES ('未分类', 'uncategorized', '默认分类')");

            // Insert default menu
            $pdo->exec("INSERT INTO `{$prefix}menus` (`name`, `slug`) VALUES ('主导航', 'primary')");
            $menuId = $pdo->lastInsertId();
            $pdo->exec("INSERT INTO `{$prefix}menu_items` (`menu_id`, `parent_id`, `title`, `url`, `type`, `sort_order`) VALUES 
                ({$menuId}, NULL, '首页', '/', 'custom', 0),
                ({$menuId}, NULL, '归档', '/archive', 'custom', 1)");

            // Write config files
            $dbConfigContent = "<?php\n\nreturn " . var_export($dbConfig, true) . ";\n";
            $appFileConfig = [
                'site_url'   => $appConfig['site_url'],
                'admin_path' => $appConfig['admin_path'],
            ];
            $appConfigContent = "<?php\n\nreturn " . var_export($appFileConfig, true) . ";\n";

            if (!is_writable($rootDir . '/config')) {
                throw new \Exception('config 目录不可写，请设置权限: chmod 755 config/');
            }

            file_put_contents($rootDir . '/config/database.php', $dbConfigContent, LOCK_EX);
            file_put_contents($rootDir . '/config/app.php', $appConfigContent, LOCK_EX);

            $success = '安装完成！';
            $step = 7;

        } catch (PDOException $e) {
            $error = '数据库连接失败: ' . $e->getMessage();
        } catch (\Exception $e) {
            $error = '安装失败: ' . $e->getMessage();
        }
    }
}

function h($str) {
    return htmlspecialchars((string) $str, ENT_QUOTES, 'UTF-8');
}

function checked($val, $expected) {
    return $val == $expected ? 'checked' : '';
}

function selected($val, $expected) {
    return $val == $expected ? 'selected' : '';
}

$envOk = PHP_VERSION_ID >= 70400 && extension_loaded('pdo') && extension_loaded('pdo_mysql') && extension_loaded('mbstring') && extension_loaded('json') && extension_loaded('fileinfo') && extension_loaded('gd');
$dirsWritable = is_writable($rootDir . '/config') && is_writable($rootDir . '/storage');

if ($step === 1) {
    $step = 2;
}

?><!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LyBlog 安装向导</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --bg: #f5f5f7;
            --card-bg: rgba(255, 255, 255, 0.72);
            --text: #1d1d1f;
            --text-secondary: #6e6e73;
            --border: rgba(0, 0, 0, 0.06);
            --accent: #000;
            --accent-hover: #333;
            --radius: 16px;
            --radius-sm: 8px;
            --shadow: 0 8px 32px rgba(0, 0, 0, 0.06);
            --glass-blur: blur(20px);
            --success: #34c759;
            --danger: #ff3b30;
            --warning: #ff9500;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'PingFang SC', 'Hiragino Sans GB', 'Microsoft YaHei', sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        body::before {
            content: '';
            position: fixed;
            top: -200px; left: -200px;
            width: 600px; height: 600px;
            background: radial-gradient(circle, rgba(0,0,0,0.04) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }
        body::after {
            content: '';
            position: fixed;
            bottom: -200px; right: -200px;
            width: 600px; height: 600px;
            background: radial-gradient(circle, rgba(0,0,0,0.03) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }
        .container { width: 100%; max-width: 560px; }
        .card {
            background: var(--card-bg);
            backdrop-filter: var(--glass-blur);
            -webkit-backdrop-filter: var(--glass-blur);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 40px;
        }
        h1 {
            font-size: 28px;
            font-weight: 600;
            letter-spacing: -0.5px;
            margin-bottom: 8px;
        }
        h2 { font-size: 18px; font-weight: 500; margin-bottom: 24px; color: var(--text-secondary); }
        h3 { font-size: 14px; font-weight: 500; margin-bottom: 16px; color: var(--text-secondary); letter-spacing: 0.5px; text-transform: uppercase; }

        .steps {
            display: flex;
            gap: 4px;
            margin-bottom: 32px;
        }
        .step-dot {
            width: 8px; height: 8px; border-radius: 50%;
            background: var(--text-secondary);
            opacity: 0.2;
            transition: all 0.3s;
        }
        .step-dot.active { opacity: 1; background: var(--accent); width: 24px; border-radius: 4px; }
        .step-dot.done { opacity: 1; background: var(--success); }

        .form-group { margin-bottom: 20px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }

        label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: var(--text-secondary);
            margin-bottom: 6px;
            letter-spacing: 0.3px;
        }

        input[type="text"],
        input[type="email"],
        input[type="password"],
        input[type="number"],
        input[type="url"],
        select {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            font-size: 15px;
            font-family: inherit;
            color: var(--text);
            background: rgba(255,255,255,0.6);
            transition: all 0.2s;
            outline: none;
        }
        input:focus, select:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(0,0,0,0.06);
            background: #fff;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 12px 28px;
            border: none;
            border-radius: var(--radius-sm);
            font-size: 15px;
            font-weight: 500;
            font-family: inherit;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            letter-spacing: 0.3px;
        }
        .btn-primary {
            background: var(--accent);
            color: #fff;
        }
        .btn-primary:hover {
            background: var(--accent-hover);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .btn-secondary {
            background: transparent;
            color: var(--text-secondary);
            border: 1px solid var(--border);
        }
        .btn-secondary:hover {
            background: rgba(0,0,0,0.03);
        }
        .btn-block { width: 100%; }

        .alert {
            padding: 12px 16px;
            border-radius: var(--radius-sm);
            margin-bottom: 20px;
            font-size: 14px;
        }
        .alert-error { background: rgba(255,59,48,0.08); color: var(--danger); border: 1px solid rgba(255,59,48,0.15); }
        .alert-success { background: rgba(52,199,89,0.08); color: var(--success); border: 1px solid rgba(52,199,89,0.15); }
        .alert-warning { background: rgba(255,149,0,0.08); color: var(--warning); border: 1px solid rgba(255,149,0,0.15); }

        .checklist { list-style: none; margin-bottom: 24px; }
        .checklist li {
            padding: 10px 0;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 14px;
        }
        .check-icon { font-size: 18px; }
        .check-ok { color: var(--success); }
        .check-fail { color: var(--danger); }
        .check-warn { color: var(--warning); }

        .actions { display: flex; gap: 12px; margin-top: 28px; }
        .text-center { text-align: center; }
        .mt-24 { margin-top: 24px; }
        .mb-16 { margin-bottom: 16px; }

        .logo { font-size: 32px; margin-bottom: 4px; }
        .complete-icon { font-size: 64px; margin-bottom: 16px; }

        @media (max-width: 600px) {
            .card { padding: 24px; }
            .form-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <?php if ($step === 0): ?>
            <h1>✅ 已安装</h1>
            <p style="color: var(--text-secondary)">LyBlog 已经安装完成。如需重新安装，请删除 config/app.php 和 config/database.php 文件。</p>
            <div class="actions">
                <a href="./" class="btn btn-primary">访问网站</a>
                <?php $adminPath = file_exists($rootDir . '/config/app.php') ? (require $rootDir . '/config/app.php')['site_url'] : ''; ?>
            </div>

        <?php elseif ($step === 2): ?>
            <h1>LyBlog</h1>
            <h2>安装向导 · 环境检查</h2>

            <div class="steps">
                <span class="step-dot active"></span>
                <span class="step-dot"></span>
                <span class="step-dot"></span>
                <span class="step-dot"></span>
            </div>

            <h3>服务器环境</h3>
            <ul class="checklist">
                <li>PHP 版本 ≥ 7.4 <?php if(PHP_VERSION_ID >= 70400): ?><span class="check-icon check-ok">✓</span><?php else: ?><span class="check-icon check-fail">✗ <?=PHP_VERSION?></span><?php endif; ?></li>
                <li>PDO 扩展 <?php if(extension_loaded('pdo')): ?><span class="check-icon check-ok">✓</span><?php else: ?><span class="check-icon check-fail">✗</span><?php endif; ?></li>
                <li>PDO_MySQL 扩展 <?php if(extension_loaded('pdo_mysql')): ?><span class="check-icon check-ok">✓</span><?php else: ?><span class="check-icon check-fail">✗</span><?php endif; ?></li>
                <li>MBString 扩展 <?php if(extension_loaded('mbstring')): ?><span class="check-icon check-ok">✓</span><?php else: ?><span class="check-icon check-fail">✗</span><?php endif; ?></li>
                <li>JSON 扩展 <?php if(extension_loaded('json')): ?><span class="check-icon check-ok">✓</span><?php else: ?><span class="check-icon check-fail">✗</span><?php endif; ?></li>
                <li>GD 扩展 <?php if(extension_loaded('gd')): ?><span class="check-icon check-ok">✓</span><?php else: ?><span class="check-icon check-warn">⚠ 可选</span><?php endif; ?></li>
                <li>FileInfo 扩展 <?php if(extension_loaded('fileinfo')): ?><span class="check-icon check-ok">✓</span><?php else: ?><span class="check-icon check-fail">✗ 必需 (文件上传)</span><?php endif; ?></li>
            </ul>

            <h3>目录权限</h3>
            <ul class="checklist">
                <li>config/ 可写 <?php if(is_writable($rootDir . '/config')): ?><span class="check-icon check-ok">✓</span><?php else: ?><span class="check-icon check-fail">✗</span><?php endif; ?></li>
                <li>storage/ 可写 <?php if(is_writable($rootDir . '/storage')): ?><span class="check-icon check-ok">✓</span><?php else: ?><span class="check-icon check-fail">✗</span><?php endif; ?></li>
            </ul>

            <div class="actions">
                <?php if ($envOk && $dirsWritable): ?>
                    <a href="install.php?step=3" class="btn btn-primary">下一步 →</a>
                <?php else: ?>
                    <button class="btn btn-primary" disabled>请先解决以上问题</button>
                <?php endif; ?>
            </div>

        <?php elseif ($step === 3): ?>
            <?php if ($error): ?><div class="alert alert-error"><?=h($error)?></div><?php endif; ?>

            <h1>数据库配置</h1>
            <h2>填写您的 MySQL 数据库信息</h2>

            <div class="steps">
                <span class="step-dot done"></span>
                <span class="step-dot active"></span>
                <span class="step-dot"></span>
                <span class="step-dot"></span>
            </div>

            <form method="post" action="install.php?step=4">
                <div class="form-group">
                    <label>数据库主机</label>
                    <input type="text" name="db_host" value="localhost" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>端口</label>
                        <input type="number" name="db_port" value="3306">
                    </div>
                    <div class="form-group">
                        <label>数据库名</label>
                        <input type="text" name="db_name" value="" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>数据库用户名</label>
                        <input type="text" name="db_user" value="" required>
                    </div>
                    <div class="form-group">
                        <label>数据库密码</label>
                        <input type="password" name="db_pass" value="">
                    </div>
                </div>
                <div class="form-group">
                    <label>表前缀</label>
                    <input type="text" name="db_prefix" value="lyblog_" placeholder="lyblog_">
                </div>
                <div class="actions">
                    <button type="submit" class="btn btn-primary">下一步 →</button>
                </div>
            </form>

        <?php elseif ($step === 4): ?>
            <?php
            $db_host   = $_POST['db_host'] ?? 'localhost';
            $db_port   = $_POST['db_port'] ?? '3306';
            $db_name   = $_POST['db_name'] ?? '';
            $db_user   = $_POST['db_user'] ?? '';
            $db_pass   = $_POST['db_pass'] ?? '';
            $db_prefix = $_POST['db_prefix'] ?? 'lyblog_';
            ?>

            <?php if ($error): ?><div class="alert alert-error"><?=h($error)?></div><?php endif; ?>

            <h1>网站配置</h1>
            <h2>设置网站基本信息和管理员账户</h2>

            <div class="steps">
                <span class="step-dot done"></span>
                <span class="step-dot done"></span>
                <span class="step-dot active"></span>
                <span class="step-dot"></span>
            </div>

            <form method="post" action="install.php?step=5">
                <input type="hidden" name="db_host" value="<?=h($db_host)?>">
                <input type="hidden" name="db_port" value="<?=h($db_port)?>">
                <input type="hidden" name="db_name" value="<?=h($db_name)?>">
                <input type="hidden" name="db_user" value="<?=h($db_user)?>">
                <input type="hidden" name="db_pass" value="<?=h($db_pass)?>">
                <input type="hidden" name="db_prefix" value="<?=h($db_prefix)?>">

                <h3>网站信息</h3>
                <div class="form-group">
                    <label>网站名称</label>
                    <input type="text" name="site_name" value="My Blog" required>
                </div>
                <div class="form-group">
                    <label>网站地址</label>
                    <input type="url" name="site_url" value="<?=h('http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'))?>" placeholder="https://example.com" required>
                </div>
                <div class="form-group">
                    <label>后台管理路径</label>
                    <input type="text" name="admin_path" value="admin" placeholder="admin">
                </div>

                <h3 style="margin-top:24px;">管理员账户</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label>用户名</label>
                        <input type="text" name="admin_user" value="admin" required autocomplete="off">
                    </div>
                    <div class="form-group">
                        <label>邮箱</label>
                        <input type="email" name="admin_email" value="" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>密码</label>
                        <input type="password" name="admin_pass" value="" required minlength="6" autocomplete="new-password">
                    </div>
                    <div class="form-group">
                        <label>确认密码</label>
                        <input type="password" name="admin_pass_confirm" value="" required minlength="6" autocomplete="new-password">
                    </div>
                </div>
                <div class="actions">
                    <a href="install.php?step=3" class="btn btn-secondary">← 返回</a>
                    <button type="submit" class="btn btn-primary">开始安装</button>
                </div>
            </form>

        <?php elseif ($step === 5): ?>
            <?php
            $admin_pass = $_POST['admin_pass'] ?? '';
            $admin_pass_confirm = $_POST['admin_pass_confirm'] ?? '';

            if (strlen($admin_pass) < 6) {
                $error = '密码长度至少 6 位';
                $step = 4;
            } elseif ($admin_pass !== $admin_pass_confirm) {
                $error = '两次输入的密码不一致';
                $step = 4;
            } else {
            ?>

            <h1>正在安装...</h1>
            <h2>请稍候，正在配置系统</h2>

            <div class="steps">
                <span class="step-dot done"></span>
                <span class="step-dot done"></span>
                <span class="step-dot done"></span>
                <span class="step-dot active"></span>
            </div>

            <form method="post" action="install.php?step=6" id="install-form" style="display:none;">
                <input type="hidden" name="action" value="install">
                <input type="hidden" name="db_host" value="<?=h($_POST['db_host'])?>">
                <input type="hidden" name="db_port" value="<?=h($_POST['db_port'])?>">
                <input type="hidden" name="db_name" value="<?=h($_POST['db_name'])?>">
                <input type="hidden" name="db_user" value="<?=h($_POST['db_user'])?>">
                <input type="hidden" name="db_pass" value="<?=h($_POST['db_pass'])?>">
                <input type="hidden" name="db_prefix" value="<?=h($_POST['db_prefix'])?>">
                <input type="hidden" name="site_name" value="<?=h($_POST['site_name'])?>">
                <input type="hidden" name="site_url" value="<?=h($_POST['site_url'])?>">
                <input type="hidden" name="admin_path" value="<?=h($_POST['admin_path'])?>">
                <input type="hidden" name="admin_user" value="<?=h($_POST['admin_user'])?>">
                <input type="hidden" name="admin_pass" value="<?=h($admin_pass)?>">
                <input type="hidden" name="admin_email" value="<?=h($_POST['admin_email'])?>">
            </form>

            <p style="text-align:center; color:var(--text-secondary);">正在创建数据表...</p>
            <div style="height:4px; background:var(--border); border-radius:2px; margin-top:12px; overflow:hidden;">
                <div id="progress-bar" style="height:100%; background:var(--accent); width:0; transition:width 0.3s; border-radius:2px;"></div>
            </div>

            <script>
                var progress = 0;
                var interval = setInterval(function() {
                    progress += Math.random() * 20;
                    if (progress > 85) progress = 85;
                    document.getElementById('progress-bar').style.width = progress + '%';
                }, 300);
                setTimeout(function() {
                    clearInterval(interval);
                    document.getElementById('progress-bar').style.width = '95%';
                    document.getElementById('install-form').submit();
                }, 1500);
            </script>
            <?php } ?>

        <?php elseif ($step === 6): ?>
            <h1>⏳</h1>
            <h2>请稍候...</h2>

        <?php elseif ($step === 7): ?>
            <?php
            $siteUrl = $_POST['site_url'] ?? '';
            $adminPath = $_POST['admin_path'] ?? 'admin';
            ?>
            <div class="text-center">
                <div class="complete-icon">🎉</div>
                <h1>安装完成！</h1>
                <p style="color:var(--text-secondary); margin-bottom:24px;">
                    网站已经成功安装。请删除 public/install.php 文件以保证安全。
                </p>
                <div class="actions">
                    <a href="<?=h($siteUrl)?>" class="btn btn-outline btn-secondary">访问网站</a>
                    <a href="<?=h($siteUrl . '/' . $adminPath)?>" class="btn btn-primary">进入后台</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
