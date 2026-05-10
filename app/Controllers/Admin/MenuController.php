<?php

namespace LyBlog\Controllers\Admin;

use LyBlog\Core\Request;
use LyBlog\Core\Session;
use LyBlog\Core\Validator;
use LyBlog\Core\Database;
use LyBlog\Models\Article;
use LyBlog\Models\Page;
use LyBlog\Models\Category;

class MenuController extends BaseAdminController
{
    public function index(Request $request)
    {
        $db = Database::getInstance();
        $menus = $db ? $db->fetchAll("SELECT * FROM {menus} ORDER BY id") : [];

        $this->adminHeader('菜单管理', 'menus');
        $this->pageHeader('菜单管理', '<a href="' . $this->adminUrl('menus/create') . '" class="btn btn-primary">新建菜单</a>');
        $this->flashMessages();

        foreach ($menus as $menu) {
            $items = $db ? $db->fetchAll("SELECT * FROM {menu_items} WHERE menu_id = ? ORDER BY sort_order, id", [$menu['id']]) : [];
            echo '<div class="card"><div class="card-header"><h2>' . htmlspecialchars($menu['name']) . ' (' . $menu['slug'] . ')</h2>';
            echo '<a href="' . $this->adminUrl('menus/' . $menu['id'] . '/items/create') . '" class="btn btn-sm btn-secondary">+ 添加菜单项</a></div>';

            if (empty($items)) {
                echo '<p style="color:var(--text-2);padding:20px 0">暂无菜单项，点击上方按钮添加</p>';
            } else {
                echo '<div class="table-wrap"><table><thead><tr><th>标题</th><th>类型</th><th>URL</th><th>排序</th><th>操作</th></tr></thead><tbody>';
                foreach ($items as $item) {
                    echo '<tr>';
                    echo '<td>' . ($item['parent_id'] ? '↳ ' : '') . htmlspecialchars($item['title']) . '</td>';
                    echo '<td>' . htmlspecialchars($item['type']) . '</td>';
                    echo '<td>' . htmlspecialchars($item['url'] ?? '-') . '</td>';
                    echo '<td>' . $item['sort_order'] . '</td>';
                    $editUrl = $this->adminUrl('menus/' . $menu['id'] . '/items/' . $item['id'] . '/edit');
                    $delUrl = $this->adminUrl('menus/' . $menu['id'] . '/items/' . $item['id'] . '/delete');
                    echo '<td><div class="table-actions"><a href="' . $editUrl . '">编辑</a><a href="' . $delUrl . '" class="delete-btn">删除</a></div></td>';
                    echo '</tr>';
                }
                echo '</tbody></table></div>';
            }
            echo '<div class="card-footer" style="display:flex;gap:8px">';
            echo '<a href="' . $this->adminUrl('menus/' . $menu['id'] . '/delete') . '" class="btn btn-sm delete-btn" style="color:var(--danger)">删除菜单</a>';
            echo '</div></div>';
        }
        $this->adminFooter();
    }

    public function create(Request $request)
    {
        $this->adminHeader('新建菜单', 'menus');
        $this->pageHeader('新建菜单', '<a href="' . $this->adminUrl('menus') . '" class="btn btn-secondary">← 返回</a>');
        echo '<div class="card"><form method="post" action="' . $this->adminUrl('menus') . '">';
        echo Session::csrfField();
        echo '<div class="form-row"><div class="form-group"><label>名称 *</label><input type="text" name="name" required></div>';
        echo '<div class="form-group"><label>Slug</label><input type="text" name="slug" placeholder="primary"></div></div>';
        echo '<button type="submit" class="btn btn-primary">创建</button></form></div>';
        $this->adminFooter();
    }

    public function store(Request $request)
    {
        if (!Session::validateCsrf()) { Session::flash('error', '安全令牌无效'); $this->redirect($this->adminUrl('menus/create')); return; }
        $name = $request->getPost('name', '');
        $slug = $request->getPost('slug', \LyBlog\Helpers\Str::slug($name));

        $db = Database::getInstance();
        $db->insert('menus', ['name' => $name, 'slug' => $slug]);
        Session::flash('success', '菜单创建成功');
        $this->redirect($this->adminUrl('menus'));
    }

    public function delete(Request $request, $id)
    {
        $db = Database::getInstance();
        $db->delete('menu_items', 'menu_id = ?', [(int) $id]);
        $db->delete('menus', 'id = ?', [(int) $id]);
        Session::flash('success', '菜单已删除');
        $this->redirect($this->adminUrl('menus'));
    }

    // Menu Items
    public function createItem(Request $request, $menuId)
    {
        $this->adminHeader('添加菜单项', 'menus');
        $this->pageHeader('添加菜单项', '<a href="' . $this->adminUrl('menus') . '" class="btn btn-secondary">← 返回</a>');
        $this->renderItemForm($menuId);
        $this->adminFooter();
    }

    public function storeItem(Request $request, $menuId)
    {
        if (!Session::validateCsrf()) { Session::flash('error', '安全令牌无效'); $this->redirect($this->adminUrl('menus')); return; }
        $data = $request->only(['title', 'url', 'type', 'target_id', 'parent_id', 'sort_order']);
        $data['menu_id'] = (int) $menuId;
        $db = Database::getInstance();
        $db->insert('menu_items', $data);
        Session::flash('success', '菜单项添加成功');
        $this->redirect($this->adminUrl('menus'));
    }

    public function editItem(Request $request, $menuId, $itemId)
    {
        $db = Database::getInstance();
        $item = $db->fetch("SELECT * FROM {menu_items} WHERE id = ?", [(int) $itemId]);
        if (!$item) { $this->notFound(); return; }
        $this->adminHeader('编辑菜单项', 'menus');
        $this->pageHeader('编辑菜单项', '<a href="' . $this->adminUrl('menus') . '" class="btn btn-secondary">← 返回</a>');
        $this->renderItemForm($menuId, $item);
        $this->adminFooter();
    }

    public function updateItem(Request $request, $menuId, $itemId)
    {
        if (!Session::validateCsrf()) { Session::flash('error', '安全令牌无效'); $this->redirect($this->adminUrl('menus')); return; }
        $data = $request->only(['title', 'url', 'type', 'target_id', 'parent_id', 'sort_order']);
        $db = Database::getInstance();
        $db->update('menu_items', $data, 'id = ?', [(int) $itemId]);
        Session::flash('success', '更新成功');
        $this->redirect($this->adminUrl('menus'));
    }

    public function deleteItem(Request $request, $menuId, $itemId)
    {
        $db = Database::getInstance();
        $db->delete('menu_items', 'id = ? OR parent_id = ?', [(int) $itemId, (int) $itemId]);
        Session::flash('success', '菜单项已删除');
        $this->redirect($this->adminUrl('menus'));
    }

    private function renderItemForm(int $menuId, array $item = null): void
    {
        $isEdit = $item !== null;
        $action = $isEdit ? $this->adminUrl('menus/' . $menuId . '/items/' . $item['id'] . '/update') : $this->adminUrl('menus/' . $menuId . '/items');

        $db = Database::getInstance();
        $items = $db ? $db->fetchAll("SELECT * FROM {menu_items} WHERE menu_id = ?", [$menuId]) : [];
        $pages = Page::getPublished();
        $categories = Category::all();

        echo '<div class="card"><form method="post" action="' . $action . '">';
        echo Session::csrfField();
        if ($isEdit) echo '<input type="hidden" name="_method" value="PUT">';

        echo '<div class="form-group"><label>标题 *</label><input type="text" name="title" value="' . htmlspecialchars($item['title'] ?? '') . '" required></div>';
        echo '<div class="form-row"><div class="form-group"><label>类型</label><select name="type">';
        foreach (['custom' => '自定义链接', 'page' => '页面', 'category' => '分类'] as $k => $v) {
            $sel = ($item['type'] ?? 'custom') === $k ? 'selected' : '';
            echo '<option value="' . $k . '" ' . $sel . '>' . $v . '</option>';
        }
        echo '</select></div>';
        echo '<div class="form-group"><label>URL</label><input type="text" name="url" value="' . htmlspecialchars($item['url'] ?? '') . '" placeholder="https://..."></div></div>';

        echo '<div class="form-row"><div class="form-group"><label>父级</label><select name="parent_id"><option value="">无</option>';
        foreach ($items as $i) {
            if ($isEdit && $i['id'] == $item['id']) continue;
            $sel = ($item['parent_id'] ?? '') == $i['id'] ? 'selected' : '';
            echo '<option value="' . $i['id'] . '" ' . $sel . '>' . htmlspecialchars($i['title']) . '</option>';
        }
        echo '</select></div>';
        echo '<div class="form-group"><label>排序</label><input type="number" name="sort_order" value="' . (int) ($item['sort_order'] ?? 0) . '"></div></div>';

        echo '<button type="submit" class="btn btn-primary">' . ($isEdit ? '更新' : '添加') . '</button>';
        echo '</form></div>';
    }
}
