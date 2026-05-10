<?php

namespace LyBlog\Controllers\Admin;

use LyBlog\Core\Request;
use LyBlog\Core\Session;
use LyBlog\Core\Validator;
use LyBlog\Models\Page;

class PageController extends BaseAdminController
{
    public function index(Request $request)
    {
        $pages = Page::all('created_at DESC');
        $this->adminHeader('页面管理', 'pages');
        $this->pageHeader('页面管理', '<a href="' . $this->adminUrl('pages/create') . '" class="btn btn-primary">新建页面</a>');
        $this->flashMessages();

        $headers = ['ID', '标题', 'Slug', '状态', '模板', '操作'];
        $rows = [];
        foreach ($pages as $p) {
            $rows[] = [
                $p['id'],
                '<a href="' . $this->siteUrl('page/' . $p['slug']) . '" target="_blank">' . htmlspecialchars($p['title']) . '</a>',
                $p['slug'],
                $this->statusBadge($p['status']),
                $p['template'] ?: '-',
                $this->actionButtons($this->adminUrl('pages/' . $p['id'] . '/edit'), $this->adminUrl('pages/' . $p['id'] . '/delete')),
            ];
        }
        $this->table($headers, $rows, '暂无页面');
        $this->adminFooter();
    }

    public function create(Request $request)
    {
        $this->adminHeader('新建页面', 'pages');
        $this->pageHeader('新建页面', '<a href="' . $this->adminUrl('pages') . '" class="btn btn-secondary">← 返回</a>');
        $this->flashMessages();
        $this->renderForm();
        $this->adminFooter();
    }

    public function store(Request $request)
    {
        if (!Session::validateCsrf()) { Session::flash('error', '安全令牌无效'); $this->redirect($this->adminUrl('pages/create')); return; }
        $data = $request->only(['title', 'slug', 'content', 'status', 'template', 'sort_order']);
        $data['author_id'] = $this->currentUser['id'];

        $v = Validator::quick($data, ['title' => 'required|max:255'], ['title' => '标题']);
        if ($v->fails()) { Session::flash('error', $v->first()); $this->redirect($this->adminUrl('pages/create')); return; }

        $id = Page::createPage($data);
        Session::flash('success', $id ? '页面创建成功' : '创建失败');
        $this->redirect($this->adminUrl($id ? 'pages/' . $id . '/edit' : 'pages/create'));
    }

    public function edit(Request $request, $id)
    {
        $page = Page::find((int) $id);
        if (!$page) { $this->notFound(); return; }
        $this->adminHeader('编辑页面', 'pages');
        $this->pageHeader('编辑: ' . htmlspecialchars($page['title']), '<a href="' . $this->adminUrl('pages') . '" class="btn btn-secondary">← 返回</a>');
        $this->flashMessages();
        $this->renderForm($page);
        $this->adminFooter();
    }

    public function update(Request $request, $id)
    {
        if (!Session::validateCsrf()) { Session::flash('error', '安全令牌无效'); $this->redirect($this->adminUrl('pages/' . $id . '/edit')); return; }
        $data = $request->only(['title', 'slug', 'content', 'status', 'template', 'sort_order']);
        Page::updatePage((int) $id, $data);
        Session::flash('success', '更新成功');
        $this->redirect($this->adminUrl('pages/' . $id . '/edit'));
    }

    public function delete(Request $request, $id)
    {
        Page::delete((int) $id);
        Session::flash('success', '页面已删除');
        $this->redirect($this->adminUrl('pages'));
    }

    private function renderForm(array $page = null): void
    {
        $isEdit = $page !== null;
        $action = $isEdit ? $this->adminUrl('pages/' . $page['id'] . '/update') : $this->adminUrl('pages');
        echo '<div class="card"><form method="post" action="' . $action . '">';
        echo Session::csrfField();
        if ($isEdit) echo '<input type="hidden" name="_method" value="PUT">';
        echo '<div class="form-group"><label>标题 *</label><input type="text" name="title" value="' . htmlspecialchars($page['title'] ?? '') . '" required></div>';
        echo '<div class="form-row"><div class="form-group"><label>Slug</label><input type="text" name="slug" value="' . htmlspecialchars($page['slug'] ?? '') . '"></div>';
        echo '<div class="form-group"><label>状态</label><select name="status"><option value="published" ' . (($page['status'] ?? 'published') === 'published' ? 'selected' : '') . '>发布</option><option value="draft" ' . (($page['status'] ?? '') === 'draft' ? 'selected' : '') . '>草稿</option></select></div></div>';
        echo '<div class="form-row"><div class="form-group"><label>模板</label><input type="text" name="template" value="' . htmlspecialchars($page['template'] ?? '') . '" placeholder="default"></div>';
        echo '<div class="form-group"><label>排序</label><input type="number" name="sort_order" value="' . (int) ($page['sort_order'] ?? 0) . '"></div></div>';
        echo '<div class="form-group"><label>内容 (Markdown)</label><textarea name="content" rows="15">' . htmlspecialchars($page['content'] ?? '') . '</textarea></div>';
        echo '<button type="submit" class="btn btn-primary">' . ($isEdit ? '更新' : '创建') . '</button>';
        echo '</form></div>';
    }
}
