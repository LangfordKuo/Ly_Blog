<?php

namespace LyBlog\Controllers\Admin;

use LyBlog\Core\Request;
use LyBlog\Core\Session;
use LyBlog\Core\Database;

class BackupController extends BaseAdminController
{
    public function index(Request $request)
    {
        $backups = [];
        $dir = STORAGE_DIR . '/backups';
        if (is_dir($dir)) {
            $files = scandir($dir);
            foreach ($files as $f) {
                if ($f === '.' || $f === '..') continue;
                if (pathinfo($f, PATHINFO_EXTENSION) === 'sql') {
                    $path = $dir . '/' . $f;
                    $backups[] = [
                        'name' => $f,
                        'size' => $this->formatBytes(filesize($path)),
                        'time' => date('Y-m-d H:i', filemtime($path)),
                    ];
                }
            }
            usort($backups, function ($a, $b) { return strcmp($b['name'], $a['name']); });
        }

        $this->adminHeader('数据库备份', 'backup');
        $this->pageHeader('数据库备份', '<a href="' . $this->adminUrl('backup/create') . '" class="btn btn-primary" onclick="return confirm(\'开始备份数据库？\')">立即备份</a>');
        $this->flashMessages();

        echo '<div class="card">';
        if (empty($backups)) {
            echo '<div class="empty"><div class="empty-icon">💾</div><p>暂无备份文件</p></div>';
        } else {
            echo '<div class="table-wrap"><table><thead><tr><th>文件名</th><th>大小</th><th>时间</th><th>操作</th></tr></thead><tbody>';
            foreach ($backups as $b) {
                echo '<tr><td>' . htmlspecialchars($b['name']) . '</td><td>' . $b['size'] . '</td><td>' . $b['time'] . '</td>';
                echo '<td><div class="table-actions">';
                echo '<a href="' . $this->adminUrl('backup/download/' . $b['name']) . '">下载</a>';
                echo '<a href="' . $this->adminUrl('backup/delete/' . urlencode($b['name'])) . '" class="delete-btn">删除</a>';
                echo '</div></td></tr>';
            }
            echo '</tbody></table></div>';
        }
        echo '</div>';
        $this->adminFooter();
    }

    public function create(Request $request)
    {
        try {
            $db = Database::getInstance();
            $pdo = $db->getPdo();
            $config = require ROOT_DIR . '/config/database.php';
            $prefix = $config['prefix'] ?? 'lyblog_';

            $backupDir = STORAGE_DIR . '/backups';
            if (!is_dir($backupDir)) mkdir($backupDir, 0755, true);

            $filename = $config['dbname'] . '_' . date('Y-m-d_His') . '.sql';
            $filepath = $backupDir . '/' . $filename;

            $output = "-- LyBlog Database Backup\n";
            $output .= "-- Date: " . date('Y-m-d H:i:s') . "\n";
            $output .= "-- Database: {$config['dbname']}\n\n";
            $output .= "SET NAMES utf8mb4;\n";
            $output .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

            $tables = $pdo->query("SHOW TABLES")->fetchAll(\PDO::FETCH_COLUMN);
            foreach ($tables as $table) {
                if ($prefix && strpos($table, $prefix) !== 0) continue;

                $createTable = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(\PDO::FETCH_NUM);
                $output .= "-- Table: {$table}\n";
                $output .= "DROP TABLE IF EXISTS `{$table}`;\n";
                $output .= $createTable[1] . ";\n\n";

                $rows = $pdo->query("SELECT * FROM `{$table}`")->fetchAll(\PDO::FETCH_ASSOC);
                if (!empty($rows)) {
                    $output .= "INSERT INTO `{$table}` VALUES\n";
                    $values = [];
                    foreach ($rows as $row) {
                        $vals = array_map(function ($v) use ($pdo) {
                            if ($v === null) return 'NULL';
                            return $pdo->quote($v);
                        }, $row);
                        $values[] = '(' . implode(', ', $vals) . ')';
                    }
                    $output .= implode(",\n", $values) . ";\n\n";
                }
            }

            $output .= "SET FOREIGN_KEY_CHECKS = 1;\n";

            file_put_contents($filepath, $output, LOCK_EX);
            Session::flash('success', '备份成功: ' . $filename);
        } catch (\Exception $e) {
            Session::flash('error', '备份失败: ' . $e->getMessage());
        }

        $this->redirect($this->adminUrl('backup'));
    }

    public function download(Request $request, $filename)
    {
        $filepath = STORAGE_DIR . '/backups/' . basename($filename);
        if (!file_exists($filepath)) {
            $this->notFound();
            return;
        }

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        exit;
    }

    public function delete(Request $request, $filename)
    {
        $filepath = STORAGE_DIR . '/backups/' . basename(urldecode($filename));
        if (file_exists($filepath)) {
            unlink($filepath);
            Session::flash('success', '备份已删除');
        }
        $this->redirect($this->adminUrl('backup'));
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' MB';
        if ($bytes >= 1024) return round($bytes / 1024, 1) . ' KB';
        return $bytes . ' B';
    }
}
