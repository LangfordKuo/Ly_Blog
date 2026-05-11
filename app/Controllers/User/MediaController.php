<?php

namespace LyBlog\Controllers\User;

use LyBlog\Core\Request;
use LyBlog\Core\Session;
use LyBlog\Core\Config;
use LyBlog\Core\Sanitizer;

class MediaController extends BaseUserController
{
    public function index(Request $request)
    {
        $uploadDir = PUBLIC_DIR . '/uploads';
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

            foreach ($allFiles as $f) {
                $path = $uploadDir . '/' . $f;
                $files[] = [
                    'name' => $f,
                    'url'  => Config::get('site_url') . '/uploads/' . $f,
                    'ext'  => strtolower(pathinfo($f, PATHINFO_EXTENSION)),
                ];
            }
        }

        $this->userHeader('媒体', 'media');
        $this->pageHeader('媒体库', '<label for="upload-file" class="btn btn-sm btn-primary" style="cursor:pointer">上传</label>');
        $this->flashMessages();

        echo '<div class="card">';
        echo '<form method="post" action="' . $this->siteUrl('user/media/upload') . '" enctype="multipart/form-data" style="display:none">';
        echo Session::csrfField();
        echo '<input type="file" name="file" id="upload-file" accept="image/*" onchange="this.form.submit()">';
        echo '</form>';

        if (empty($files)) {
            echo '<div class="empty"><p>暂无文件</p></div>';
        } else {
            echo '<div class="media-grid">';
            foreach ($files as $f) {
                $isImage = in_array($f['ext'], ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']);
                echo '<div class="media-item">';
                if ($isImage) {
                    echo '<img src="' . $f['url'] . '" alt="" onclick="copyUrl(\'' . $f['url'] . '\')" style="cursor:pointer">';
                } else {
                    echo '<div style="height:120px;display:flex;align-items:center;justify-content:center;font-size:32px;background:var(--bg)">📄</div>';
                }
                echo '<div class="media-name">' . htmlspecialchars($f['name']) . '</div>';
                echo '</div>';
            }
            echo '</div>';
        }
        echo '</div>';
        echo '<script>function copyUrl(u){if(navigator.clipboard&&window.isSecureContext){navigator.clipboard.writeText(u).then(function(){alert("已复制")}).catch(function(){fallback(u)})}else{fallback(u)}}function fallback(t){var a=document.createElement("textarea");a.value=t;a.style.position="fixed";a.style.opacity="0";document.body.appendChild(a);a.select();try{document.execCommand("copy");alert("已复制")}catch(e){prompt("复制:",t)}document.body.removeChild(a)}</script>';

        $this->userFooter();
    }

    public function upload(Request $request)
    {
        if (!Session::validateCsrf()) {
            $this->redirect($this->siteUrl('user/media'));
            return;
        }

        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            Session::flash('error', '上传失败');
            $this->redirect($this->siteUrl('user/media'));
            return;
        }

        $file = $_FILES['file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];

        if (!in_array($ext, $allowed)) {
            Session::flash('error', '不支持的文件类型');
            $this->redirect($this->siteUrl('user/media'));
            return;
        }

        if ($file['size'] > 5 * 1024 * 1024) {
            Session::flash('error', '文件过大，最大 5MB');
            $this->redirect($this->siteUrl('user/media'));
            return;
        }

        $uploadDir = PUBLIC_DIR . '/uploads';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $filename = Sanitizer::filename(pathinfo($file['name'], PATHINFO_FILENAME)) . '.' . $ext;
        $filepath = $uploadDir . '/' . $filename;
        $i = 1;
        while (file_exists($filepath)) {
            $filename = Sanitizer::filename(pathinfo($file['name'], PATHINFO_FILENAME)) . '-' . $i . '.' . $ext;
            $filepath = $uploadDir . '/' . $filename;
            $i++;
        }

        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            Session::flash('success', '上传成功');
        } else {
            Session::flash('error', '保存失败');
        }

        $this->redirect($this->siteUrl('user/media'));
    }
}
