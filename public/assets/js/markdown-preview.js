/**
 * LyBlog Markdown Live Preview
 * Zero-dependency client-side Markdown renderer + split-pane preview
 */
(function(){
    'use strict';

    // ===== MARKDOWN PARSER =====
    function md2html(md) {
        if (!md) return '<p style="color:var(--text-2)">暂无内容</p>';
        var html = md;

        // Escape HTML (except for our generated tags)
        html = html.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

        // Code blocks (```)
        html = html.replace(/```(\w*)\n?([\s\S]*?)```/g, function(_, lang, code) {
            return '<pre' + (lang ? ' data-lang="' + lang + '"' : '') + '><code>' + code + '</code></pre>';
        });

        // Inline code (`)
        html = html.replace(/`([^`]+)`/g, '<code>$1</code>');

        // Images ![alt](url)
        html = html.replace(/!\[([^\]]*)\]\(([^)]+)\)/g, '<img src="$2" alt="$1">');

        // Links [text](url)
        html = html.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank">$1</a>');

        // Headers
        html = html.replace(/^#### (.+)$/gm, '<h4>$1</h4>');
        html = html.replace(/^### (.+)$/gm, '<h3>$1</h3>');
        html = html.replace(/^## (.+)$/gm, '<h2>$1</h2>');
        html = html.replace(/^# (.+)$/gm, '<h1>$1</h1>');

        // Horizontal rules
        html = html.replace(/^(---|\*\*\*|___)$/gm, '<hr>');

        // Bold + Italic
        html = html.replace(/\*\*\*(.+?)\*\*\*/g, '<strong><em>$1</em></strong>');
        html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
        html = html.replace(/__(.+?)__/g, '<strong>$1</strong>');
        html = html.replace(/\*(.+?)\*/g, '<em>$1</em>');
        html = html.replace(/_(.+?)_/g, '<em>$1</em>');
        html = html.replace(/~~(.+?)~~/g, '<del>$1</del>');

        // Blockquotes
        html = html.replace(/^&gt; (.+)$/gm, '<blockquote>$1</blockquote>');
        // Merge consecutive blockquotes
        html = html.replace(/<\/blockquote>\n<blockquote>/g, '\n');

        // Ordered lists
        html = html.replace(/^(\d+)\. (.+)$/gm, '<li>$2</li>');
        html = html.replace(/(<li>[\s\S]*?<\/li>)/g, function(m) {
            if (m.indexOf('</li>\n<li>') !== -1 || m.match(/^\d+\./m)) {
                return '<ol>' + m.replace(/<\/li>\n<li>/g, '</li>\n<li>') + '</ol>';
            }
            return m;
        });

        // Unordered lists
        html = html.replace(/^[-*] (.+)$/gm, '<li>$1</li>');

        // Wrap adjacent <li> in <ul> or <ol>
        html = html.replace(/((?:<li>[\s\S]*?<\/li>\n?)+)/g, function(m) {
            if (m.indexOf('<ol>') === -1 && m.indexOf('<ul>') === -1) {
                return '<ul>' + m + '</ul>';
            }
            return m;
        });
        // Remove duplicate uls
        html = html.replace(/<\/ul>\n<ul>/g, '\n');

        // Paragraphs (double newlines)
        html = html.replace(/\n\n/g, '</p><p>');
        html = '<p>' + html + '</p>';

        // Clean up empty paragraphs
        html = html.replace(/<p>\s*<\/p>/g, '');
        // Cleanup block-level elements inside <p>
        html = html.replace(/<p>(<(h[1-4]|pre|ul|ol|blockquote|hr|table)[^>]*>[\s\S]*?<\/\2>)<\/p>/g, '$1');
        html = html.replace(/<p>(<(pre|h[1-4])[\s\S]*?<\/\2>)<\/p>/g, '$1');

        return html;
    }

    // ===== PREVIEW PANEL =====
    function initPreview(textareaSelector) {
        var textarea = document.querySelector(textareaSelector);
        if (!textarea) return;

        // Create preview container
        var preview = document.createElement('div');
        preview.id = 'md-preview';
        preview.style.cssText = 'display:none;min-height:300px;padding:20px;background:var(--card-bg);border:1px solid var(--border);border-radius:8px;font-size:15px;line-height:1.8;overflow-y:auto;max-height:600px';
        preview.className = 'article-content';

        // Create toggle bar
        var bar = document.createElement('div');
        bar.style.cssText = 'display:flex;align-items:center;gap:8px;margin-bottom:8px';
        bar.innerHTML = '<button type="button" class="btn btn-sm btn-secondary" id="md-edit-btn" style="display:none">✏ 编辑</button>' +
                        '<button type="button" class="btn btn-sm btn-secondary" id="md-preview-btn">👁 预览</button>' +
                        '<span style="font-size:12px;color:var(--text-2);margin-left:auto" id="md-hint">点击预览查看渲染效果</span>';

        textarea.parentNode.insertBefore(bar, textarea.nextSibling);
        textarea.parentNode.insertBefore(preview, bar.nextSibling);

        var editBtn = document.getElementById('md-edit-btn');
        var previewBtn = document.getElementById('md-preview-btn');
        var hint = document.getElementById('md-hint');

        function showEdit() {
            textarea.style.display = 'block';
            preview.style.display = 'none';
            editBtn.style.display = 'none';
            previewBtn.style.display = 'inline-flex';
            if (hint) hint.textContent = '点击预览查看渲染效果';
        }

        function showPreview() {
            preview.innerHTML = md2html(textarea.value);
            textarea.style.display = 'none';
            preview.style.display = 'block';
            editBtn.style.display = 'inline-flex';
            previewBtn.style.display = 'none';
            if (hint) hint.textContent = '实时预览模式';
        }

        previewBtn.addEventListener('click', showPreview);
        editBtn.addEventListener('click', showEdit);

        // Auto-refresh preview on input (debounced)
        var timer;
        textarea.addEventListener('input', function() {
            if (preview.style.display !== 'none') {
                clearTimeout(timer);
                timer = setTimeout(function() {
                    preview.innerHTML = md2html(textarea.value);
                }, 300);
            }
        });
    }

    // Auto-init on page load for article editor textarea
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            initPreview('textarea[name="content"]');
        });
    } else {
        initPreview('textarea[name="content"]');
    }
})();
