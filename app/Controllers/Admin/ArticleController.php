<?php

namespace LyBlog\Controllers\Admin;

use LyBlog\Core\Request;
use LyBlog\Core\Session;
use LyBlog\Core\Validator;
use LyBlog\Models\Article;
use LyBlog\Models\Category;
use LyBlog\Models\Tag;

class ArticleController extends BaseAdminController
{
    protected $activeMenu = 'articles';

    public function index(Request $request)
    {
        $page = (int) ($request->getQuery('page', 1));
        $status = $request->getQuery('status', '');
        $search = $request->getQuery('search', '');
        $categoryId = (int) ($request->getQuery('category_id', 0));

        $where = '1=1';
        $params = [];

        if ($status) { $where .= ' AND status = ?'; $params[] = $status; }
        if ($categoryId) { $where .= ' AND category_id = ?'; $params[] = $categoryId; }
        if ($search) { $where .= ' AND (title LIKE ? OR content LIKE ?)'; $params[] = "%{$search}%"; $params[] = "%{$search}%"; }

        $data = Article::paginate($page, 15, $where, $params, 'created_at DESC');
        $categories = Category::all('name ASC');

        $this->adminHeader('文章管理', 'articles');
        $this->pageHeader('文章管理', '<a href="' . $this->adminUrl('articles/create') . '" class="btn btn-primary">写文章</a>');
        $this->flashMessages();

        echo '<div class="card"><form method="get" class="filter-bar">';
        echo '<select name="status" style="width:auto"><option value="">全部状态</option>';
        echo '<option value="published" ' . ($status === 'published' ? 'selected' : '') . '>已发布</option>';
        echo '<option value="draft" ' . ($status === 'draft' ? 'selected' : '') . '>草稿</option>';
        echo '<option value="private" ' . ($status === 'private' ? 'selected' : '') . '>私密</option>';
        echo '</select>';
        echo '<select name="category_id" style="width:auto"><option value="0">全部分类</option>';
        foreach ($categories as $cat) echo '<option value="' . $cat['id'] . '" ' . ($categoryId == $cat['id'] ? 'selected' : '') . '>' . htmlspecialchars($cat['name']) . '</option>';
        echo '</select>';
        echo '<input type="text" name="search" value="' . htmlspecialchars($search) . '" placeholder="搜索..." style="width:200px">';
        echo '<button type="submit" class="btn btn-secondary btn-sm">筛选</button>';
        echo '</form></div>';

        // Batch actions
        echo '<form method="post" action="' . $this->adminUrl('articles/batch') . '" id="batch-form">';
        echo Session::csrfField();
        echo '<div class="card" style="padding:12px 16px;margin-bottom:12px;display:flex;align-items:center;gap:12px">';
        echo '<input type="checkbox" onclick="document.querySelectorAll(\'.batch-check\').forEach(c=>c.checked=this.checked)" style="margin-right:8px"> 全选';
        echo '<select name="action" style="width:auto;margin-left:auto">';
        echo '<option value="delete">删除选中</option>';
        echo '<option value="publish">设为发布</option>';
        echo '<option value="draft">设为草稿</option>';
        echo '</select>';
        echo '<button type="submit" class="btn btn-sm btn-secondary" onclick="return confirm(\'确定执行批量操作？\')">执行</button>';
        echo '</div>';

        $headers = ['', 'ID', '标题', '分类', '状态', '阅读', '发布时间', '操作'];
        $rows = [];
        foreach ($data['items'] as $a) {
            $catName = '';
            foreach ($categories as $c) { if ($c['id'] == $a['category_id']) $catName = $c['name']; }
            $rows[] = [
                '<input type="checkbox" name="ids[]" value="' . $a['id'] . '" class="batch-check">',
                $a['id'],
                ($a['is_pinned'] ? '📌 ' : '') . '<a href="' . $this->siteUrl('article/' . $a['slug']) . '" target="_blank">' . htmlspecialchars($a['title']) . '</a>',
                htmlspecialchars($catName),
                $this->statusBadge($a['status']),
                $a['views'],
                $a['published_at'] ?? '-',
                $this->actionButtons($this->adminUrl('articles/' . $a['id'] . '/edit'), $this->adminUrl('articles/' . $a['id'] . '/delete')),
            ];
        }
        $this->table($headers, $rows, '暂无文章');
        echo '</form>';
        $this->pagination($data, $this->adminUrl('articles'));
        $this->adminFooter();
    }

    public function create(Request $request)
    {
        $this->adminHeader('写文章', 'articles');
        $this->pageHeader('写文章', '<a href="' . $this->adminUrl('articles') . '" class="btn btn-secondary">← 返回</a>');
        $this->flashMessages();
        $this->renderForm();
        $this->adminFooter();
    }

    public function store(Request $request)
    {
        if (!Session::validateCsrf()) { Session::flash('error', '安全令牌无效'); $this->redirect($this->adminUrl('articles/create')); return; }

        $data = $request->only(['title', 'slug', 'content', 'excerpt', 'cover_image', 'status', 'category_id', 'published_at', 'tags']);
        $data['author_id'] = $this->currentUser['id'];

        if (empty($data['slug'])) $data['slug'] = \LyBlog\Helpers\Str::slug($data['title']);

        $v = Validator::quick($data, ['title' => 'required|min:1|max:255'], ['title' => '标题']);
        if ($v->fails()) { Session::flash('error', $v->first()); $this->redirect($this->adminUrl('articles/create')); return; }

        $id = Article::createArticle($data);
        if ($id) {
            Session::flash('success', '文章创建成功');
            $this->redirect($this->adminUrl('articles/' . $id . '/edit'));
        } else {
            Session::flash('error', '创建失败');
            $this->redirect($this->adminUrl('articles/create'));
        }
    }

    public function edit(Request $request, $id)
    {
        $article = Article::find((int) $id);
        if (!$article) { $this->notFound(); return; }

        $this->adminHeader('编辑文章', 'articles');
        $this->pageHeader('编辑: ' . htmlspecialchars($article['title']), '<a href="' . $this->adminUrl('articles') . '" class="btn btn-secondary">← 返回</a>');
        $this->flashMessages();
        $this->renderForm($article);
        $this->adminFooter();
    }

    public function update(Request $request, $id)
    {
        if (!Session::validateCsrf()) { Session::flash('error', '安全令牌无效'); $this->redirect($this->adminUrl('articles/' . $id . '/edit')); return; }

        $data = $request->only(['title', 'slug', 'content', 'excerpt', 'cover_image', 'status', 'category_id', 'published_at', 'tags']);
        if (!empty($data['title'])) {
            $v = Validator::quick($data, ['title' => 'required|max:255'], ['title' => '标题']);
            if ($v->fails()) { Session::flash('error', $v->first()); $this->redirect($this->adminUrl('articles/' . $id . '/edit')); return; }
        }

        Article::updateArticle((int) $id, $data);
        Session::flash('success', '更新成功');
        $this->redirect($this->adminUrl('articles/' . $id . '/edit'));
    }

    public function delete(Request $request, $id)
    {
        Article::delete((int) $id);
        Session::flash('success', '文章已删除');
        $this->redirect($this->adminUrl('articles'));
    }

    public function batch(Request $request)
    {
        if (!Session::validateCsrf()) {
            Session::flash('error', '安全令牌无效');
            $this->redirect($this->adminUrl('articles'));
            return;
        }

        $ids = $request->getPost('ids', []);
        $action = $request->getPost('action', 'delete');

        if (empty($ids) || !is_array($ids)) {
            Session::flash('error', '请选择文章');
            $this->redirect($this->adminUrl('articles'));
            return;
        }

        $count = 0;
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id <= 0) continue;

            switch ($action) {
                case 'delete':
                    Article::delete($id);
                    $count++;
                    break;
                case 'publish':
                    Article::update($id, ['status' => 'published', 'published_at' => date('Y-m-d H:i:s')]);
                    $count++;
                    break;
                case 'draft':
                    Article::update($id, ['status' => 'draft']);
                    $count++;
                    break;
            }
        }

        Session::flash('success', "已处理 {$count} 篇文章");
        $this->redirect($this->adminUrl('articles'));
    }

    private function renderForm(array $article = null): void
    {
        $isEdit = $article !== null;
        $action = $isEdit ? $this->adminUrl('articles/' . $article['id'] . '/update') : $this->adminUrl('articles');
        $tags = $isEdit ? Article::getTags($article['id']) : [];
        $tagNames = array_column($tags, 'name');
        $categories = Category::all('name ASC');

        echo '<div class="card"><form method="post" action="' . $action . '">';
        echo Session::csrfField();
        if ($isEdit) echo '<input type="hidden" name="_method" value="PUT">';

        echo '<div class="form-group"><label>标题 *</label><input type="text" name="title" value="' . htmlspecialchars($article['title'] ?? '') . '" required></div>';

        echo '<div class="form-row">';
        echo '<div class="form-group"><label>Slug</label><input type="text" name="slug" value="' . htmlspecialchars($article['slug'] ?? '') . '" placeholder="留空自动生成"><p class="help-text">URL 友好标识，留空自动从标题生成</p></div>';
        echo '<div class="form-group"><label>状态</label><select name="status">';
        foreach (['draft' => '草稿', 'published' => '发布', 'private' => '私密'] as $k => $v) {
            $sel = ($article['status'] ?? 'draft') === $k ? 'selected' : '';
            echo '<option value="' . $k . '" ' . $sel . '>' . $v . '</option>';
        }
        echo '</select>';
        echo '<label style="margin-top:8px;display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer">';
        echo '<input type="hidden" name="is_pinned" value="0">';
        $checked = ($article['is_pinned'] ?? 0) ? 'checked' : '';
        echo '<input type="checkbox" name="is_pinned" value="1" ' . $checked . '> 置顶文章';
        echo '</label>';
        echo '</div>';
        echo '</div>';

        echo '<div class="form-row">';
        echo '<div class="form-group"><label>分类</label><select name="category_id"><option value="">未分类</option>';
        foreach ($categories as $cat) {
            $sel = ($article['category_id'] ?? '') == $cat['id'] ? 'selected' : '';
            echo '<option value="' . $cat['id'] . '" ' . $sel . '>' . htmlspecialchars($cat['name']) . '</option>';
        }
        echo '</select></div>';
        echo '<div class="form-group"><label>发布时间</label><input type="datetime-local" name="published_at" value="' . htmlspecialchars(($article['published_at'] ?? date('Y-m-d\TH:i')) ? date('Y-m-d\TH:i', strtotime($article['published_at'] ?? 'now')) : '') . '"></div>';
        echo '</div>';

        echo '<div class="form-group"><label>标签</label><input type="text" name="tags" value="' . htmlspecialchars(implode(', ', $tagNames)) . '" placeholder="用逗号分隔多个标签"></div>';
        echo '<div class="form-group"><label>封面图 URL</label><input type="url" name="cover_image" value="' . htmlspecialchars($article['cover_image'] ?? '') . '" placeholder="https://..."></div>';
        echo '<div class="form-group"><label>摘要</label><textarea name="excerpt" rows="2">' . htmlspecialchars($article['excerpt'] ?? '') . '</textarea><p class="help-text">留空自动从内容截取</p></div>';

        echo '<div class="form-group"><label>内容 (Markdown)</label><textarea name="content" rows="20">' . htmlspecialchars($article['content'] ?? '') . '</textarea></div>';

        echo '<button type="submit" class="btn btn-primary">' . ($isEdit ? '更新' : '发布') . '</button>';
        echo '</form></div>';

        echo '<script src="' . $this->siteUrl('assets/js/markdown-preview.js') . '"></script>';
        echo '<script>var LYBLOG_UPLOAD_URL="' . $this->adminUrl('media/quick-upload') . '";var LYBLOG_CSRF="' . Session::csrfToken() . '";</script>';
        echo '<script src="' . $this->siteUrl('assets/js/image-upload.js') . '"></script>';

        // Auto-save draft script
        $draftKey = 'lyblog_draft_' . ($article['id'] ?? 'new');
        echo '<div id="draft-banner" style="display:none;background:rgba(255,149,0,0.08);color:#ff9500;padding:10px 16px;border-radius:8px;margin-top:12px;font-size:13px;display:none">
            📝 检测到未保存的草稿 · <a href="#" onclick="restoreDraft();return false">恢复</a> · <a href="#" onclick="clearDraft();return false" style="color:#ff3b30">丢弃</a>
        </div>';
        echo '<script>
            (function(){
                var form = document.querySelector(".card form");
                if (!form) return;
                var key = "' . $draftKey . '";
                var saveTimer = null;
                var banner = document.getElementById("draft-banner");
                var lastSaved = "";

                // Check for saved draft on load
                var saved = localStorage.getItem(key);
                if (saved && banner) {
                    try {
                        var data = JSON.parse(saved);
                        if (data.content && data.content.trim()) {
                            banner.style.display = "block";
                            window._draftData = data;
                        }
                    } catch(e) {}
                }

                // Auto-save every 20 seconds if form changed
                function autoSave() {
                    var data = {
                        title: (form.querySelector("[name=title]")||{}).value || "",
                        slug: (form.querySelector("[name=slug]")||{}).value || "",
                        content: (form.querySelector("[name=content]")||{}).value || "",
                        excerpt: (form.querySelector("[name=excerpt]")||{}).value || "",
                        tags: (form.querySelector("[name=tags]")||{}).value || "",
                        cover_image: (form.querySelector("[name=cover_image]")||{}).value || "",
                        category_id: (form.querySelector("[name=category_id]")||{}).value || "",
                        saved_at: new Date().toLocaleString()
                    };
                    var current = JSON.stringify(data);
                    if (current !== lastSaved) {
                        localStorage.setItem(key, current);
                        lastSaved = current;
                    }
                }
                setInterval(autoSave, 20000);
                autoSave();

                // Clear draft on form submit
                form.addEventListener("submit", function() { localStorage.removeItem(key); });

                // Restore function
                window.restoreDraft = function() {
                    if (!window._draftData) return;
                    var d = window._draftData;
                    var setVal = function(name, val) { var el = form.querySelector("[name="+name+"]"); if (el && val) el.value = val; };
                    setVal("title", d.title);
                    setVal("slug", d.slug);
                    setVal("content", d.content);
                    setVal("excerpt", d.excerpt);
                    setVal("tags", d.tags);
                    setVal("cover_image", d.cover_image);
                    setVal("category_id", d.category_id);
                    if (banner) banner.style.display = "none";
                };
                window.clearDraft = function() {
                    localStorage.removeItem(key);
                    if (banner) banner.style.display = "none";
                };
            })();
        </script>';
    }
}
