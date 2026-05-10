<?php

namespace LyBlog\Controllers\Admin;

use LyBlog\Core\Request;
use LyBlog\Core\Session;
use LyBlog\Core\Sanitizer;

class MediaController extends BaseAdminController
{
    public function index(Request $request)
    {
        $page = (int) ($request->getQuery('page', 1));
        $perPage = 40;
        $uploadDir = STORAGE_DIR . '/uploads';

        $files = [];
        if (is_dir($uploadDir)) {
            $allFiles = array_diff(scandir($uploadDir), ['.', '..']);
            $allFiles = array_values(array_filter($allFiles, function ($f) use ($uploadDir) {
                $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
                return in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'mp4', 'pdf', 'zip']);
            }));
            usort($allFiles, function ($a, $b) use ($uploadDir) {
                return filemtime($uploadDir . '/' . $b) - filemtime($uploadDir . '/' . $a);
            });

            $total = count($allFiles);
            $offset = ($page - 1) * $perPage;
            $pageFiles = array_slice($allFiles, $offset, $perPage);

            foreach ($pageFiles as $f) {
                $path = $uploadDir . '/' . $f;
                $files[] = [
                    'name' => $f,
                    'size' => filesize($path),
                    'url'  => Config('site_url') . '/storage/uploads/' . $f,
                    'time' => date('Y-m-d H:i', filemtime($path)),
                    'ext'  => strtolower(pathinfo($f, PATHINFO_EXTENSION)),
                ];
            }

            $data = [
                'items' => $files,
                'total' => $total,
                'current_page' => $page,
                'last_page' => max(1, (int) ceil($total / $perPage)),
                'has_more' => ($page * $perPage) < $total,
            ];
        } else {
            $data = ['items' => [], 'total' => 0, 'current_page' => 1, 'last_page' => 1, 'has_more' => false];
        }

        $this->adminHeader('媒体管理', 'media');
        $this->pageHeader('媒体管理', '<label for="upload-input" class="btn btn-primary" style="cursor:pointer">上传文件</label>');
        $this->flashMessages();

        echo '<div class="card">';
        echo '<form id="upload-form" method="post" action="' . $this->adminUrl('media/upload') . '" enctype="multipart/form-data" style="margin-bottom:16px;display:none">';
        echo Session::csrfField();
        echo '<input type="file" name="file" id="upload-input" accept="image/*,video/*,.pdf,.zip" onchange="document.getElementById(\'upload-form\').submit()">';
        echo '</form>';

        if (empty($files)) {
            echo '<div class="empty"><div class="empty-icon">🖼️</div><p>暂无媒体文件</p><p style="font-size:13px;margin-top:8px">点击"上传文件"按钮开始上传</p></div>';
        } else {
            echo '<div class="media-grid">';
            foreach ($files as $f) {
                $isImage = in_array($f['ext'], ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']);
                echo '<div class="media-item">';
                if ($isImage) {
                    echo '<img src="' . $f['url'] . '" alt="' . $f['name'] . '" onclick="copyUrl(\'' . $f['url'] . '\')" style="cursor:pointer">';
                } else {
                    echo '<div style="height:120px;display:flex;align-items:center;justify-content:center;font-size:32px;background:var(--bg)">📄</div>';
                }
                echo '<div class="media-name">' . htmlspecialchars($f['name']) . '</div>';
                echo '<div class="media-actions">';
                echo '<button onclick="copyUrl(\'' . $f['url'] . '\')" style="font-size:12px;padding:2px 8px;border:1px solid var(--border);border-radius:4px;background:#fff;cursor:pointer">复制</button>';
                echo '<a href="' . $this->adminUrl('media/delete?file=' . urlencode($f['name'])) . '" class="delete-btn" style="font-size:12px;padding:2px 8px">删除</a>';
                echo '</div></div>';
            }
            echo '</div>';
        }
        if (isset($data) && $data['last_page'] > 1) {
            $this->pagination($data, $this->adminUrl('media'));
        }
        echo '</div>';

        echo '<script>
            function copyUrl(url) {
                navigator.clipboard.writeText(url).then(function() {
                    alert("URL 已复制: " + url);
                }).catch(function() {
                    prompt("复制此 URL:", url);
                });
            }
        </script>';
        $this->adminFooter();
    }

    public function upload(Request $request)
    {
        if (!Session::validateCsrf()) {
            $this->json(['error' => '安全令牌无效'], 403);
            return;
        }

        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            Session::flash('error', '上传失败');
            $this->redirect($this->adminUrl('media'));
            return;
        }

        $file = $_FILES['file'];
        $maxSize = 10 * 1024 * 1024; // 10MB
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'mp4', 'pdf', 'zip', 'doc', 'docx', 'xls', 'xlsx'];

        if ($file['size'] > $maxSize) {
            Session::flash('error', '文件过大，最大 10MB');
            $this->redirect($this->adminUrl('media'));
            return;
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed)) {
            Session::flash('error', '不支持的文件类型');
            $this->redirect($this->adminUrl('media'));
            return;
        }

        $uploadDir = STORAGE_DIR . '/uploads';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $filename = Sanitizer::filename(pathinfo($file['name'], PATHINFO_FILENAME)) . '.' . $ext;
        $filepath = $uploadDir . '/' . $filename;

        // Avoid overwrite
        $i = 1;
        while (file_exists($filepath)) {
            $filename = Sanitizer::filename(pathinfo($file['name'], PATHINFO_FILENAME)) . '-' . $i . '.' . $ext;
            $filepath = $uploadDir . '/' . $filename;
            $i++;
        }

        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            Session::flash('success', '上传成功');
        } else {
            Session::flash('error', '保存文件失败');
        }

        $this->redirect($this->adminUrl('media'));
    }

    public function delete(Request $request)
    {
        $filename = $request->getQuery('file', '');
        if (empty($filename)) {
            $this->redirect($this->adminUrl('media'));
            return;
        }

        $filepath = STORAGE_DIR . '/uploads/' . basename($filename);
        if (file_exists($filepath)) {
            unlink($filepath);
            Session::flash('success', '文件已删除');
        } else {
            Session::flash('error', '文件不存在');
        }

        $this->redirect($this->adminUrl('media'));
    }
}
