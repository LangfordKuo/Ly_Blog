<?php
/**
 * LyBlog Admin — Redirect to index.php front controller
 */

$rootDir = dirname(__DIR__);

if (!file_exists($rootDir . '/config/app.php') || !file_exists($rootDir . '/config/database.php')) {
    header('Location: ./install.php');
    exit;
}

$config = require $rootDir . '/config/app.php';
$adminPath = $config['admin_path'] ?? 'admin';

// Redirect to the admin path through the front controller
header('Location: /' . trim($adminPath, '/'));
exit;
