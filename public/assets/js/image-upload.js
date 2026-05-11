/**
 * LyBlog Drag-Drop & Paste Image Upload
 * Zero-dependency, works with article editor textarea
 */
(function(){
    'use strict';

    function initImageUpload(textareaSelector, uploadUrl, csrfToken) {
        var textarea = document.querySelector(textareaSelector);
        if (!textarea) return;

        // ===== DRAG & DROP =====
        var dropOverlay = document.createElement('div');
        dropOverlay.style.cssText = 'display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(99,102,241,0.08);z-index:9999;pointer-events:none;display:flex;align-items:center;justify-content:center;font-size:24px;color:var(--primary, #6366f1);font-weight:600';
        dropOverlay.textContent = '📁 释放图片以上传';
        dropOverlay.style.display = 'none';
        document.body.appendChild(dropOverlay);

        var dragCounter = 0;
        document.addEventListener('dragenter', function(e) { e.preventDefault(); dragCounter++; dropOverlay.style.display = 'flex'; });
        document.addEventListener('dragleave', function(e) { e.preventDefault(); dragCounter--; if (dragCounter <= 0) { dropOverlay.style.display = 'none'; dragCounter = 0; } });
        document.addEventListener('dragover', function(e) { e.preventDefault(); });
        document.addEventListener('drop', function(e) {
            e.preventDefault();
            dragCounter = 0;
            dropOverlay.style.display = 'none';

            var files = e.dataTransfer.files;
            if (files.length > 0) {
                uploadFiles(files);
            }
        });

        // ===== PASTE HANDLER =====
        textarea.addEventListener('paste', function(e) {
            var items = e.clipboardData.items;
            for (var i = 0; i < items.length; i++) {
                if (items[i].type.indexOf('image') !== -1) {
                    e.preventDefault();
                    var blob = items[i].getAsFile();
                    uploadFiles([blob]);
                    break;
                }
            }
        });

        // ===== UPLOAD FUNCTION =====
        function uploadFiles(files) {
            Array.prototype.forEach.call(files, function(file) {
                if (!file.type.match(/^image\/(jpeg|png|gif|webp|svg\+xml)$/)) return;

                // Show uploading indicator
                var placeholder = '[上传中...]';
                insertAtCursor(textarea, placeholder);

                var formData = new FormData();
                formData.append('file', file);
                formData.append('_csrf_token', csrfToken);

                fetch(uploadUrl, {
                    method: 'POST',
                    body: formData,
                    headers: {'X-Requested-With': 'XMLHttpRequest'}
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    // Replace placeholder with actual markdown image
                    var text = textarea.value;
                    var mdImage = data.success ? '![' + (data.name || file.name) + '](' + data.url + ')' : '[上传失败]';
                    textarea.value = text.replace(placeholder, mdImage);
                })
                .catch(function() {
                    textarea.value = textarea.value.replace(placeholder, '[上传失败]');
                });
            });
        }

        // Helper: insert text at cursor position
        function insertAtCursor(el, text) {
            if (el.selectionStart !== undefined) {
                var start = el.selectionStart;
                var end = el.selectionEnd;
                var before = el.value.substring(0, start);
                var after = el.value.substring(end);
                el.value = before + text + after;
                el.selectionStart = el.selectionEnd = start + text.length;
            } else {
                el.value += text;
            }
        }
    }

    if (typeof LYBLOG_UPLOAD_URL !== 'undefined' && typeof LYBLOG_CSRF !== 'undefined') {
        initImageUpload('textarea[name="content"]', LYBLOG_UPLOAD_URL, LYBLOG_CSRF);
    }
})();
