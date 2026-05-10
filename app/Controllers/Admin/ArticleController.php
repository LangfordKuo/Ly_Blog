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

        $headers = ['ID', '标题', '分类', '状态', '阅读', '发布时间', '操作'];
        $rows = [];
        foreach ($data['items'] as $a) {
            $catName = '';
            foreach ($categories as $c) { if ($c['id'] == $a['category_id']) $catName = $c['name']; }
            $rows[] = [
                $a['id'],
                '<a href="' . $this->siteUrl('article/' . $a['slug']) . '" target="_blank">' . htmlspecialchars($a['title']) . '</a>',
                htmlspecialchars($catName),
                $this->statusBadge($a['status']),
                $a['views'],
                $a['published_at'] ?? '-',
                $this->actionButtons($this->adminUrl('articles/' . $a['id'] . '/edit'), $this->adminUrl('articles/' . $a['id'] . '/delete')),
            ];
        }
        $this->table($headers, $rows, '暂无文章');
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
        echo '</select></div>';
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
    }
}
