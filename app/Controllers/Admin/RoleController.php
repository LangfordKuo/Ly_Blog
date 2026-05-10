<?php

namespace LyBlog\Controllers\Admin;

use LyBlog\Core\Request;
use LyBlog\Core\Session;
use LyBlog\Core\Validator;
use LyBlog\Models\Role;

class RoleController extends BaseAdminController
{
    public function index(Request $request)
    {
        $roles = Role::all('id ASC');
        $this->adminHeader('角色管理', 'roles');
        $this->pageHeader('角色管理', '<a href="' . $this->adminUrl('roles/create') . '" class="btn btn-primary">新建角色</a>');
        $this->flashMessages();

        $headers = ['ID', '名称', 'Slug', '描述', '系统', '操作'];
        $rows = [];
        foreach ($roles as $r) {
            $actions = $r['is_system'] ? '<span style="color:var(--text-2);font-size:13px">系统角色</span>' : $this->actionButtons($this->adminUrl('roles/' . $r['id'] . '/edit'), $this->adminUrl('roles/' . $r['id'] . '/delete'));
            $rows[] = [
                $r['id'],
                htmlspecialchars($r['name']),
                $r['slug'],
                htmlspecialchars($r['description'] ?? '-'),
                $r['is_system'] ? '✅' : '-',
                $actions,
            ];
        }
        $this->table($headers, $rows, '暂无角色');
        $this->adminFooter();
    }

    public function create(Request $request)
    {
        $this->adminHeader('新建角色', 'roles');
        $this->pageHeader('新建角色', '<a href="' . $this->adminUrl('roles') . '" class="btn btn-secondary">← 返回</a>');
        $this->flashMessages();
        $this->renderForm();
        $this->adminFooter();
    }

    public function store(Request $request)
    {
        if (!Session::validateCsrf()) { Session::flash('error', '安全令牌无效'); $this->redirect($this->adminUrl('roles/create')); return; }

        $name = $request->getPost('name', '');
        $slug = $request->getPost('slug', '');
        $desc = $request->getPost('description', '');
        $perms = $request->getPost('permissions', []);

        if (empty($slug)) $slug = \LyBlog\Helpers\Str::slug($name);

        $v = Validator::quick(['name' => $name], ['name' => 'required|max:50'], ['name' => '名称']);
        if ($v->fails()) { Session::flash('error', $v->first()); $this->redirect($this->adminUrl('roles/create')); return; }

        $permData = [];
        foreach ($perms as $p) $permData[$p] = true;

        $id = Role::create(['name' => $name, 'slug' => $slug, 'description' => $desc, 'permissions' => json_encode($permData)]);
        Session::flash('success', $id ? '角色创建成功' : '创建失败');
        $this->redirect($this->adminUrl('roles'));
    }

    public function edit(Request $request, $id)
    {
        $role = Role::find((int) $id);
        if (!$role) { $this->notFound(); return; }
        $this->adminHeader('编辑角色', 'roles');
        $this->pageHeader('编辑: ' . htmlspecialchars($role['name']), '<a href="' . $this->adminUrl('roles') . '" class="btn btn-secondary">← 返回</a>');
        $this->flashMessages();
        $this->renderForm($role);
        $this->adminFooter();
    }

    public function update(Request $request, $id)
    {
        if (!Session::validateCsrf()) { Session::flash('error', '安全令牌无效'); $this->redirect($this->adminUrl('roles/' . $id . '/edit')); return; }

        $role = Role::find((int) $id);
        if ($role['is_system']) { Session::flash('error', '系统角色不可修改权限'); $this->redirect($this->adminUrl('roles')); return; }

        $name = $request->getPost('name', '');
        $desc = $request->getPost('description', '');
        $perms = $request->getPost('permissions', []);
        $permData = [];
        foreach ($perms as $p) $permData[$p] = true;

        Role::update((int) $id, ['name' => $name, 'description' => $desc, 'permissions' => json_encode($permData)]);
        Session::flash('success', '更新成功');
        $this->redirect($this->adminUrl('roles'));
    }

    public function delete(Request $request, $id)
    {
        $role = Role::find((int) $id);
        if ($role && $role['is_system']) {
            Session::flash('error', '系统角色不可删除');
        } else {
            Role::delete((int) $id);
            Session::flash('success', '角色已删除');
        }
        $this->redirect($this->adminUrl('roles'));
    }

    private function renderForm(array $role = null): void
    {
        $isEdit = $role !== null;
        $action = $isEdit ? $this->adminUrl('roles/' . $role['id'] . '/update') : $this->adminUrl('roles');
        $perms = $isEdit ? Role::getPermissions($role['id']) : [];
        $allPerms = Role::getAllPermissions();

        echo '<div class="card"><form method="post" action="' . $action . '">';
        echo Session::csrfField();
        if ($isEdit) echo '<input type="hidden" name="_method" value="PUT">';

        echo '<div class="form-row"><div class="form-group"><label>名称 *</label><input type="text" name="name" value="' . htmlspecialchars($role['name'] ?? '') . '" required></div>';
        echo '<div class="form-group"><label>Slug</label><input type="text" name="slug" value="' . htmlspecialchars($role['slug'] ?? '') . '" ' . ($isEdit ? 'readonly' : '') . ' placeholder="自动生成"></div></div>';
        echo '<div class="form-group"><label>描述</label><input type="text" name="description" value="' . htmlspecialchars($role['description'] ?? '') . '"></div>';

        echo '<h3 style="font-size:16px;font-weight:500;margin-bottom:12px;margin-top:24px">权限</h3>';
        echo '<div class="checkbox-grid">';
        foreach ($allPerms as $key => $label) {
            $checked = isset($perms[$key]) && $perms[$key] === true;
            echo '<label class="checkbox-item"><input type="checkbox" name="permissions[]" value="' . $key . '" ' . ($checked ? 'checked' : '') . '> ' . htmlspecialchars($label) . '</label>';
        }
        echo '</div>';

        echo '<button type="submit" class="btn btn-primary" style="margin-top:20px">' . ($isEdit ? '更新' : '创建') . '</button>';
        echo '</form></div>';
    }
}
