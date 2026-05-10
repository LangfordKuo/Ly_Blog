<?php

namespace LyBlog\Controllers\Admin;

use LyBlog\Core\Request;
use LyBlog\Core\Session;
use LyBlog\Core\Validator;
use LyBlog\Models\User;
use LyBlog\Models\Role;

class UserController extends BaseAdminController
{
    public function index(Request $request)
    {
        $page = (int) ($request->getQuery('page', 1));
        $search = $request->getQuery('search', '');

        $where = '1=1';
        $params = [];
        if ($search) { $where .= ' AND (username LIKE ? OR email LIKE ? OR display_name LIKE ?)'; $params = ["%{$search}%", "%{$search}%", "%{$search}%"]; }

        $data = User::paginate($page, 15, $where, $params, 'created_at DESC');

        $this->adminHeader('用户管理', 'users');
        $this->pageHeader('用户管理', '<a href="' . $this->adminUrl('users/create') . '" class="btn btn-primary">新建用户</a>');
        $this->flashMessages();

        echo '<div class="card"><form method="get" class="filter-bar">';
        echo '<input type="text" name="search" value="' . htmlspecialchars($search) . '" placeholder="搜索用户名/邮箱..." style="width:220px">';
        echo '<button type="submit" class="btn btn-secondary btn-sm">搜索</button>';
        echo '</form></div>';

        $roles = Role::all();
        $roleMap = [];
        foreach ($roles as $r) $roleMap[$r['id']] = $r['name'];

        $headers = ['ID', '用户名', '显示名', '邮箱', '角色', '状态', '最后登录', '操作'];
        $rows = [];
        foreach ($data['items'] as $u) {
            $rows[] = [
                $u['id'],
                htmlspecialchars($u['username']),
                htmlspecialchars($u['display_name'] ?? $u['username']),
                htmlspecialchars($u['email']),
                htmlspecialchars($roleMap[$u['role_id']] ?? '-'),
                $this->statusBadge($u['status'] ? 'active' : 'disabled'),
                $u['last_login'] ?? '-',
                $this->actionButtons($this->adminUrl('users/' . $u['id'] . '/edit'), $this->adminUrl('users/' . $u['id'] . '/delete')),
            ];
        }
        $this->table($headers, $rows, '暂无用户');
        $this->pagination($data, $this->adminUrl('users'));
        $this->adminFooter();
    }

    public function create(Request $request)
    {
        $this->adminHeader('新建用户', 'users');
        $this->pageHeader('新建用户', '<a href="' . $this->adminUrl('users') . '" class="btn btn-secondary">← 返回</a>');
        $this->flashMessages();
        $this->renderForm();
        $this->adminFooter();
    }

    public function store(Request $request)
    {
        if (!Session::validateCsrf()) { Session::flash('error', '安全令牌无效'); $this->redirect($this->adminUrl('users/create')); return; }

        $data = $request->only(['username', 'email', 'password', 'display_name', 'role_id', 'status', 'bio', 'website']);
        $v = Validator::quick($data, [
            'username' => 'required|min:3|max:50',
            'email'    => 'required|email',
            'password' => 'required|min:6',
        ], ['username' => '用户名', 'email' => '邮箱', 'password' => '密码']);

        if ($v->fails()) { Session::flash('error', $v->first()); $this->redirect($this->adminUrl('users/create')); return; }

        if (User::findByUsername($data['username'])) { Session::flash('error', '用户名已存在'); $this->redirect($this->adminUrl('users/create')); return; }
        if (User::findByEmail($data['email'])) { Session::flash('error', '邮箱已被使用'); $this->redirect($this->adminUrl('users/create')); return; }

        $id = User::createUser($data);
        Session::flash('success', $id ? '用户创建成功' : '创建失败');
        $this->redirect($this->adminUrl('users'));
    }

    public function edit(Request $request, $id)
    {
        $user = User::find((int) $id);
        if (!$user) { $this->notFound(); return; }
        $this->adminHeader('编辑用户', 'users');
        $this->pageHeader('编辑: ' . htmlspecialchars($user['username']), '<a href="' . $this->adminUrl('users') . '" class="btn btn-secondary">← 返回</a>');
        $this->flashMessages();
        $this->renderForm($user);
        $this->adminFooter();
    }

    public function update(Request $request, $id)
    {
        if (!Session::validateCsrf()) { Session::flash('error', '安全令牌无效'); $this->redirect($this->adminUrl('users/' . $id . '/edit')); return; }

        $data = $request->only(['username', 'email', 'password', 'display_name', 'role_id', 'status', 'bio', 'website']);
        if (empty($data['password'])) unset($data['password']);

        User::updateUser((int) $id, $data);
        Session::flash('success', '更新成功');
        $this->redirect($this->adminUrl('users/' . $id . '/edit'));
    }

    public function delete(Request $request, $id)
    {
        if ((int) $id === (int) $this->currentUser['id']) {
            Session::flash('error', '不能删除自己');
        } else {
            User::delete((int) $id);
            Session::flash('success', '用户已删除');
        }
        $this->redirect($this->adminUrl('users'));
    }

    private function renderForm(array $user = null): void
    {
        $isEdit = $user !== null;
        $action = $isEdit ? $this->adminUrl('users/' . $user['id'] . '/update') : $this->adminUrl('users');
        $roles = Role::all();

        echo '<div class="card"><form method="post" action="' . $action . '">';
        echo Session::csrfField();
        if ($isEdit) echo '<input type="hidden" name="_method" value="PUT">';

        echo '<div class="form-row"><div class="form-group"><label>用户名 *</label><input type="text" name="username" value="' . htmlspecialchars($user['username'] ?? '') . '" required></div>';
        echo '<div class="form-group"><label>邮箱 *</label><input type="email" name="email" value="' . htmlspecialchars($user['email'] ?? '') . '" required></div></div>';

        echo '<div class="form-group"><label>密码 ' . ($isEdit ? '(留空不修改)' : '*') . '</label><input type="password" name="password" ' . ($isEdit ? '' : 'required') . ' autocomplete="new-password"></div>';

        echo '<div class="form-row"><div class="form-group"><label>显示名称</label><input type="text" name="display_name" value="' . htmlspecialchars($user['display_name'] ?? '') . '"></div>';
        echo '<div class="form-group"><label>角色</label><select name="role_id">';
        foreach ($roles as $r) {
            $sel = ($user['role_id'] ?? 5) == $r['id'] ? 'selected' : '';
            echo '<option value="' . $r['id'] . '" ' . $sel . '>' . htmlspecialchars($r['name']) . '</option>';
        }
        echo '</select></div></div>';

        echo '<div class="form-row"><div class="form-group"><label>个人网站</label><input type="url" name="website" value="' . htmlspecialchars($user['website'] ?? '') . '"></div>';
        echo '<div class="form-group"><label>状态</label><select name="status"><option value="1" ' . (($user['status'] ?? 1) == 1 ? 'selected' : '') . '>启用</option><option value="0" ' . (($user['status'] ?? '') === '0' ? 'selected' : '') . '>禁用</option></select></div></div>';

        echo '<div class="form-group"><label>简介</label><textarea name="bio" rows="3">' . htmlspecialchars($user['bio'] ?? '') . '</textarea></div>';
        echo '<button type="submit" class="btn btn-primary">' . ($isEdit ? '更新' : '创建') . '</button>';
        echo '</form></div>';
    }
}
