<?php

namespace LyBlog\Controllers\User;

use LyBlog\Core\Request;
use LyBlog\Core\Session;
use LyBlog\Core\Validator;
use LyBlog\Models\Article;
use LyBlog\Models\Category;

class ArticleController extends BaseUserController
{
    public function index(Request $request)
    {
        $userId = $this->currentUser['id'];
        $page = (int) ($request->getQuery('page', 1));
        $status = $request->getQuery('status', '');

        $where = 'author_id = ?';
        $params = [$userId];
        if ($status) { $where .= ' AND status = ?'; $params[] = $status; }

        $data = Article::paginate($page, 15, $where, $params, 'created_at DESC');

        $this->userHeader('我的文章', 'articles');
        $this->pageHeader('我的文章', '<a href="' . $this->siteUrl('user/articles/create') . '" class="btn btn-primary">写文章</a>');
        $this->flashMessages();

        echo '<div class="card"><form method="get" class="filter-bar">';
        echo '<select name="status" style="width:auto"><option value="">全部</option>';
        foreach (['published' => '已发布', 'draft' => '草稿', 'private' => '私密'] as $k => $v) {
            echo '<option value="' . $k . '" ' . ($status === $k ? 'selected' : '') . '>' . $v . '</option>';
        }
        echo '</select>';
        echo '<button type="submit" class="btn btn-secondary btn-sm">筛选</button>';
        echo '</form></div>';

        $headers = ['ID', '标题', '状态', '阅读', '日期', '操作'];
        $rows = [];
        foreach ($data['items'] as $a) {
            $date = $a['status'] === 'published' ? ($a['published_at'] ?? $a['created_at']) : $a['created_at'];
            $rows[] = [
                $a['id'],
                '<a href="' . $this->siteUrl('article/' . $a['slug']) . '" target="_blank">' . htmlspecialchars($a['title']) . '</a>',
                $this->statusBadge($a['status']),
                $a['views'],
                date('Y-m-d', strtotime($date)),
                $this->actionButtons($this->siteUrl('user/articles/' . $a['id'] . '/edit'), $this->siteUrl('user/articles/' . $a['id'] . '/delete')),
            ];
        }
        echo '<div class="card">';
        echo '<div class="table-wrap"><table><thead><tr>';
        foreach ($headers as $h) echo '<th>' . $h . '</th>';
        echo '</tr></thead><tbody>';
        foreach ($rows as $row) { echo '<tr>'; foreach ($row as $cell) echo '<td>' . $cell . '</td>'; echo '</tr>'; }
        echo '</tbody></table></div></div>';

        if ($data['last_page'] > 1) {
            echo '<div class="pagination">';
            $sep = '?'; $q = $status ? 'status=' . $status : '';
            if ($data['current_page'] > 1) echo '<a href="' . $sep . 'page=' . ($data['current_page'] - 1) . ($q ? '&' . $q : '') . '">←</a>';
            for ($i = max(1, $data['current_page'] - 2); $i <= min($data['last_page'], $data['current_page'] + 2); $i++) {
                $cls = $i === $data['current_page'] ? 'active' : '';
                echo '<a href="' . $sep . 'page=' . $i . ($q ? '&' . $q : '') . '" class="' . $cls . '">' . $i . '</a>';
            }
            if ($data['current_page'] < $data['last_page']) echo '<a href="' . $sep . 'page=' . ($data['current_page'] + 1) . ($q ? '&' . $q : '') . '">→</a>';
            echo '</div>';
        }

        $this->userFooter();
    }

    public function create(Request $request)
    {
        $this->userHeader('写文章', 'articles');
        $this->pageHeader('写文章', '<a href="' . $this->siteUrl('user/articles') . '" class="btn btn-secondary">← 返回</a>');
        $this->flashMessages();
        $this->renderForm();
        $this->userFooter();
    }

    public function store(Request $request)
    {
        if (!Session::validateCsrf()) {
            Session::flash('error', '安全令牌无效');
            $this->redirect($this->siteUrl('user/articles/create'));
            return;
        }

        $data = $request->only(['title', 'slug', 'content', 'excerpt', 'cover_image', 'status', 'category_id', 'tags']);
        $data['author_id'] = $this->currentUser['id'];

        if (!$this->canPublish() && $data['status'] === 'published') {
            $data['status'] = 'draft';
        }

        $v = Validator::quick($data, ['title' => 'required|max:255'], ['title' => '标题']);
        if ($v->fails()) {
            Session::flash('error', $v->first());
            $this->redirect($this->siteUrl('user/articles/create'));
            return;
        }

        $id = Article::createArticle($data);
        if ($id) {
            Session::flash('success', '文章创建成功');
            $this->redirect($this->siteUrl('user/articles/' . $id . '/edit'));
        } else {
            Session::flash('error', '创建失败');
            $this->redirect($this->siteUrl('user/articles/create'));
        }
    }

    public function edit(Request $request, $id)
    {
        $article = Article::find((int) $id);
        if (!$article || $article['author_id'] != $this->currentUser['id']) {
            $this->notFound();
            return;
        }

        $this->userHeader('编辑文章', 'articles');
        $this->pageHeader('编辑: ' . htmlspecialchars($article['title']), '<a href="' . $this->siteUrl('user/articles') . '" class="btn btn-secondary">← 返回</a>');
        $this->flashMessages();
        $this->renderForm($article);
        $this->userFooter();
    }

    public function update(Request $request, $id)
    {
        if (!Session::validateCsrf()) {
            Session::flash('error', '安全令牌无效');
            $this->redirect($this->siteUrl('user/articles/' . $id . '/edit'));
            return;
        }

        $article = Article::find((int) $id);
        if (!$article || $article['author_id'] != $this->currentUser['id']) {
            $this->notFound();
            return;
        }

        $data = $request->only(['title', 'slug', 'content', 'excerpt', 'cover_image', 'status', 'category_id', 'tags']);
        if (!$this->canPublish() && isset($data['status']) && $data['status'] === 'published' && $article['status'] !== 'published') {
            $data['status'] = $article['status'];
        }

        Article::updateArticle((int) $id, $data);
        Session::flash('success', '更新成功');
        $this->redirect($this->siteUrl('user/articles/' . $id . '/edit'));
    }

    public function delete(Request $request, $id)
    {
        $article = Article::find((int) $id);
        if ($article && $article['author_id'] == $this->currentUser['id']) {
            Article::delete((int) $id);
            Session::flash('success', '文章已删除');
        }
        $this->redirect($this->siteUrl('user/articles'));
    }

    private function canPublish(): bool
    {
        return $this->hasPerm('article.publish') || $this->hasPerm('*');
    }

    private function renderForm(array $article = null): void
    {
        $isEdit = $article !== null;
        $action = $isEdit ? $this->siteUrl('user/articles/' . $article['id'] . '/update') : $this->siteUrl('user/articles');
        $tags = $isEdit ? Article::getTags($article['id']) : [];
        $tagNames = array_column($tags, 'name');
        $categories = Category::all('name ASC');
        $canPublish = $this->canPublish();

        echo '<div class="card"><form method="post" action="' . $action . '">';
        echo Session::csrfField();
        if ($isEdit) echo '<input type="hidden" name="_method" value="PUT">';

        echo '<div class="form-group"><label>标题 *</label><input type="text" name="title" value="' . htmlspecialchars($article['title'] ?? '') . '" required></div>';
        echo '<div class="form-row"><div class="form-group"><label>Slug</label><input type="text" name="slug" value="' . htmlspecialchars($article['slug'] ?? '') . '" placeholder="自动生成"></div>';
        echo '<div class="form-group"><label>状态</label><select name="status">';
        foreach (['draft' => '草稿', 'published' => '发布', 'private' => '私密'] as $k => $v) {
            if ($k === 'published' && !$canPublish) continue;
            $sel = ($article['status'] ?? 'draft') === $k ? 'selected' : '';
            echo '<option value="' . $k . '" ' . $sel . '>' . $v . '</option>';
        }
        echo '</select>';
        if (!$canPublish) echo '<p class="help-text" style="font-size:12px;color:var(--text-2)">发布权限需管理员审批</p>';
        echo '</div></div>';

        echo '<div class="form-group"><label>分类</label><select name="category_id"><option value="">未分类</option>';
        foreach ($categories as $cat) {
            $sel = ($article['category_id'] ?? '') == $cat['id'] ? 'selected' : '';
            echo '<option value="' . $cat['id'] . '" ' . $sel . '>' . htmlspecialchars($cat['name']) . '</option>';
        }
        echo '</select></div>';

        echo '<div class="form-group"><label>标签</label><input type="text" name="tags" value="' . htmlspecialchars(implode(', ', $tagNames)) . '" placeholder="逗号分隔"></div>';
        echo '<div class="form-group"><label>封面图 URL</label><input type="url" name="cover_image" value="' . htmlspecialchars($article['cover_image'] ?? '') . '"></div>';
        echo '<div class="form-group"><label>摘要</label><textarea name="excerpt" rows="2">' . htmlspecialchars($article['excerpt'] ?? '') . '</textarea></div>';
        echo '<div class="form-group"><label>内容 (Markdown)</label><textarea name="content" rows="16">' . htmlspecialchars($article['content'] ?? '') . '</textarea></div>';

        echo '<button type="submit" class="btn btn-primary">' . ($isEdit ? '更新' : '发布') . '</button>';
        echo '</form></div>';
    }
}
