<?php

namespace LyBlog\Controllers\Admin;

use LyBlog\Core\Request;
use LyBlog\Core\Session;
use LyBlog\Core\Validator;
use LyBlog\Models\Tag;

class TagController extends BaseAdminController
{
    public function index(Request $request)
    {
        $search = $request->getQuery('search', '');
        if ($search) {
            $tags = Tag::query('name LIKE ?', ["%{$search}%"], 'name ASC');
        } else {
            $tags = Tag::getCloud(100);
        }

        $this->adminHeader('标签管理', 'tags');
        $this->pageHeader('标签管理', '<a href="' . $this->adminUrl('tags/create') . '" class="btn btn-primary">新建标签</a>');
        $this->flashMessages();

        echo '<div class="card"><form method="get" class="filter-bar">';
        echo '<input type="text" name="search" value="' . htmlspecialchars($search) . '" placeholder="搜索标签..." style="width:200px">';
        echo '<button type="submit" class="btn btn-secondary btn-sm">搜索</button>';
        echo '</form></div>';

        $headers = ['ID', '名称', 'Slug', '文章数', '操作'];
        $rows = [];
        foreach ($tags as $t) {
            $rows[] = [
                $t['id'],
                htmlspecialchars($t['name']),
                $t['slug'],
                $t['article_count'] ?? Tag::getArticleCount($t['id']),
                $this->actionButtons($this->adminUrl('tags/' . $t['id'] . '/edit'), $this->adminUrl('tags/' . $t['id'] . '/delete')),
            ];
        }
        $this->table($headers, $rows, '暂无标签');
        $this->adminFooter();
    }

    public function create(Request $request)
    {
        $this->adminHeader('新建标签', 'tags');
        $this->pageHeader('新建标签', '<a href="' . $this->adminUrl('tags') . '" class="btn btn-secondary">← 返回</a>');
        $this->flashMessages();
        echo '<div class="card"><form method="post" action="' . $this->adminUrl('tags') . '">';
        echo Session::csrfField();
        echo '<div class="form-group"><label>名称 *</label><input type="text" name="name" required></div>';
        echo '<button type="submit" class="btn btn-primary">创建</button></form></div>';
        $this->adminFooter();
    }

    public function store(Request $request)
    {
        if (!Session::validateCsrf()) { Session::flash('error', '安全令牌无效'); $this->redirect($this->adminUrl('tags/create')); return; }
        $name = $request->getPost('name', '');
        $v = Validator::quick(['name' => $name], ['name' => 'required|max:100'], ['name' => '名称']);
        if ($v->fails()) { Session::flash('error', $v->first()); $this->redirect($this->adminUrl('tags/create')); return; }

        Tag::createTag($name);
        Session::flash('success', '标签创建成功');
        $this->redirect($this->adminUrl('tags'));
    }

    public function edit(Request $request, $id)
    {
        $tag = Tag::find((int) $id);
        if (!$tag) { $this->notFound(); return; }
        $this->adminHeader('编辑标签', 'tags');
        $this->pageHeader('编辑: ' . htmlspecialchars($tag['name']), '<a href="' . $this->adminUrl('tags') . '" class="btn btn-secondary">← 返回</a>');
        $this->flashMessages();
        echo '<div class="card"><form method="post" action="' . $this->adminUrl('tags/' . $tag['id'] . '/update') . '">';
        echo Session::csrfField();
        echo '<input type="hidden" name="_method" value="PUT">';
        echo '<div class="form-group"><label>名称 *</label><input type="text" name="name" value="' . htmlspecialchars($tag['name']) . '" required></div>';
        echo '<button type="submit" class="btn btn-primary">更新</button></form></div>';
        $this->adminFooter();
    }

    public function update(Request $request, $id)
    {
        if (!Session::validateCsrf()) { Session::flash('error', '安全令牌无效'); $this->redirect($this->adminUrl('tags/' . $id . '/edit')); return; }
        Tag::updateTag((int) $id, ['name' => $request->getPost('name', '')]);
        Session::flash('success', '更新成功');
        $this->redirect($this->adminUrl('tags'));
    }

    public function delete(Request $request, $id)
    {
        Tag::delete((int) $id);
        Session::flash('success', '标签已删除');
        $this->redirect($this->adminUrl('tags'));
    }
}
