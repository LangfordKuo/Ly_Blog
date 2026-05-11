<?php

namespace LyBlog\Controllers\User;

use LyBlog\Core\Request;
use LyBlog\Core\Session;
use LyBlog\Core\Validator;
use LyBlog\Models\User;

class ProfileController extends BaseUserController
{
    public function index(Request $request)
    {
        $user = $this->currentUser;

        $this->userHeader('个人资料', 'profile');
        $this->pageHeader('个人资料');
        $this->flashMessages();

        echo '<div class="card"><form method="post" action="' . $this->siteUrl('user/profile') . '">';
        echo Session::csrfField();

        echo '<div class="form-row">';
        echo '<div class="form-group"><label>用户名</label><input type="text" value="' . htmlspecialchars($user['username']) . '" disabled>';
        echo '<p class="help-text" style="font-size:12px;color:var(--text-2)">用户名不可修改</p></div>';
        echo '<div class="form-group"><label>邮箱</label><input type="email" name="email" value="' . htmlspecialchars($user['email'] ?? '') . '"></div>';
        echo '</div>';

        echo '<div class="form-row">';
        echo '<div class="form-group"><label>显示名称</label><input type="text" name="display_name" value="' . htmlspecialchars($user['display_name'] ?? '') . '"></div>';
        echo '<div class="form-group"><label>个人网站</label><input type="url" name="website" value="' . htmlspecialchars($user['website'] ?? '') . '"></div>';
        echo '</div>';

        echo '<div class="form-group"><label>个人简介</label><textarea name="bio" rows="3">' . htmlspecialchars($user['bio'] ?? '') . '</textarea></div>';
        echo '<button type="submit" class="btn btn-primary">保存资料</button>';
        echo '</form></div>';

        // Password change form
        echo '<div class="card" style="margin-top:20px"><h3 style="font-size:16px;font-weight:500;margin-bottom:16px">修改密码</h3>';
        echo '<form method="post" action="' . $this->siteUrl('user/profile/password') . '">';
        echo Session::csrfField();
        echo '<div class="form-group"><label>当前密码</label><input type="password" name="current_password" required></div>';
        echo '<div class="form-row"><div class="form-group"><label>新密码</label><input type="password" name="new_password" required minlength="6"></div>';
        echo '<div class="form-group"><label>确认新密码</label><input type="password" name="new_password_confirm" required minlength="6"></div></div>';
        echo '<button type="submit" class="btn btn-secondary">修改密码</button>';
        echo '</form></div>';

        $this->userFooter();
    }

    public function update(Request $request)
    {
        if (!Session::validateCsrf()) {
            Session::flash('error', '安全令牌无效');
            $this->redirect($this->siteUrl('user/profile'));
            return;
        }

        $data = $request->only(['email', 'display_name', 'website', 'bio']);

        $v = Validator::quick($data, ['email' => 'required|email|max:100'], ['email' => '邮箱']);
        if ($v->fails()) {
            Session::flash('error', $v->first());
            $this->redirect($this->siteUrl('user/profile'));
            return;
        }

        $existing = User::findByEmail($data['email']);
        if ($existing && $existing['id'] != $this->currentUser['id']) {
            Session::flash('error', '邮箱已被其他用户使用');
            $this->redirect($this->siteUrl('user/profile'));
            return;
        }

        User::updateUser($this->currentUser['id'], $data);
        Session::flash('success', '资料已更新');
        $this->redirect($this->siteUrl('user/profile'));
    }

    public function updatePassword(Request $request)
    {
        if (!Session::validateCsrf()) {
            Session::flash('error', '安全令牌无效');
            $this->redirect($this->siteUrl('user/profile'));
            return;
        }

        $current = $request->getPost('current_password', '');
        $newPwd  = $request->getPost('new_password', '');
        $confirm = $request->getPost('new_password_confirm', '');

        if (!User::verifyPassword($this->currentUser['id'], $current)) {
            Session::flash('error', '当前密码不正确');
            $this->redirect($this->siteUrl('user/profile'));
            return;
        }

        $v = Validator::quick(
            ['new_password' => $newPwd],
            ['new_password' => 'required|min:6|max:100'],
            ['new_password' => '新密码']
        );

        if ($v->fails()) {
            Session::flash('error', $v->first());
            $this->redirect($this->siteUrl('user/profile'));
            return;
        }

        if ($newPwd !== $confirm) {
            Session::flash('error', '两次输入的新密码不一致');
            $this->redirect($this->siteUrl('user/profile'));
            return;
        }

        User::updateUser($this->currentUser['id'], ['password' => $newPwd]);
        Session::flash('success', '密码已修改，请重新登录');
        Auth::logout();
        $this->redirect($this->siteUrl('login'));
    }
}
