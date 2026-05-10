<?php

namespace LyBlog\Controllers\Admin;

use LyBlog\Core\Request;
use LyBlog\Core\Session;
use LyBlog\Core\Validator;
use LyBlog\Models\Category;

class CategoryController extends BaseAdminController
{
    public function index(Request $request)
    {
        $categories = Category::getTree();
        $flatList = Category::getFlatList();

        $this->adminHeader('分类管理', 'categories');
        $this->pageHeader('分类管理', '<a href="' . $this->adminUrl('categories/create') . '" class="btn btn-primary">新建分类</a>');
        $this->flashMessages();

        $headers = ['ID', '名称', 'Slug', '文章数', '排序', '操作'];
        $rows = [];
        foreach ($flatList as $c) {
            $rows[] = [
                $c['id'],
                htmlspecialchars($c['display'] ?? $c['name']),
                $c['slug'],
                Category::getArticleCount($c['id']),
                $c['sort_order'],
                $this->actionButtons($this->adminUrl('categories/' . $c['id'] . '/edit'), $this->adminUrl('categories/' . $c['id'] . '/delete')),
            ];
        }
        $this->table($headers, $rows, '暂无分类');
        $this->adminFooter();
    }

    public function create(Request $request)
    {
        $this->adminHeader('新建分类', 'categories');
        $this->pageHeader('新建分类', '<a href="' . $this->adminUrl('categories') . '" class="btn btn-secondary">← 返回</a>');
        $this->flashMessages();
        $this->renderForm();
        $this->adminFooter();
    }

    public function store(Request $request)
    {
        if (!Session::validateCsrf()) { Session::flash('error', '安全令牌无效'); $this->redirect($this->adminUrl('categories/create')); return; }
        $data = $request->only(['name', 'slug', 'description', 'parent_id', 'sort_order']);
        $v = Validator::quick($data, ['name' => 'required|max:100'], ['name' => '名称']);
        if ($v->fails()) { Session::flash('error', $v->first()); $this->redirect($this->adminUrl('categories/create')); return; }

        Category::createCategory($data);
        Session::flash('success', '分类创建成功');
        $this->redirect($this->adminUrl('categories'));
    }

    public function edit(Request $request, $id)
    {
        $cat = Category::find((int) $id);
        if (!$cat) { $this->notFound(); return; }
        $this->adminHeader('编辑分类', 'categories');
        $this->pageHeader('编辑: ' . htmlspecialchars($cat['name']), '<a href="' . $this->adminUrl('categories') . '" class="btn btn-secondary">← 返回</a>');
        $this->flashMessages();
        $this->renderForm($cat);
        $this->adminFooter();
    }

    public function update(Request $request, $id)
    {
        if (!Session::validateCsrf()) { Session::flash('error', '安全令牌无效'); $this->redirect($this->adminUrl('categories/' . $id . '/edit')); return; }
        $data = $request->only(['name', 'slug', 'description', 'parent_id', 'sort_order']);
        Category::updateCategory((int) $id, $data);
        Session::flash('success', '更新成功');
        $this->redirect($this->adminUrl('categories'));
    }

    public function delete(Request $request, $id)
    {
        Category::deleteCategory((int) $id);
        Session::flash('success', '分类已删除');
        $this->redirect($this->adminUrl('categories'));
    }

    private function renderForm(array $cat = null): void
    {
        $isEdit = $cat !== null;
        $action = $isEdit ? $this->adminUrl('categories/' . $cat['id'] . '/update') : $this->adminUrl('categories');
        $categories = Category::all('name ASC');

        echo '<div class="card"><form method="post" action="' . $action . '">';
        echo Session::csrfField();
        if ($isEdit) echo '<input type="hidden" name="_method" value="PUT">';

        echo '<div class="form-group"><label>名称 *</label><input type="text" name="name" value="' . htmlspecialchars($cat['name'] ?? '') . '" required></div>';
        echo '<div class="form-row"><div class="form-group"><label>Slug</label><input type="text" name="slug" value="' . htmlspecialchars($cat['slug'] ?? '') . '"></div>';
        echo '<div class="form-group"><label>父分类</label><select name="parent_id"><option value="">无 (顶级)</option>';
        foreach ($categories as $c) {
            if ($isEdit && $c['id'] == $cat['id']) continue;
            $sel = ($cat['parent_id'] ?? '') == $c['id'] ? 'selected' : '';
            echo '<option value="' . $c['id'] . '" ' . $sel . '>' . htmlspecialchars($c['name']) . '</option>';
        }
        echo '</select></div></div>';
        echo '<div class="form-row"><div class="form-group"><label>描述</label><input type="text" name="description" value="' . htmlspecialchars($cat['description'] ?? '') . '"></div>';
        echo '<div class="form-group"><label>排序</label><input type="number" name="sort_order" value="' . (int) ($cat['sort_order'] ?? 0) . '"></div></div>';
        echo '<button type="submit" class="btn btn-primary">' . ($isEdit ? '更新' : '创建') . '</button>';
        echo '</form></div>';
    }
}
