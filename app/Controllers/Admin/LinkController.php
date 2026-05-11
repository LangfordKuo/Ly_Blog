<?php

namespace LyBlog\Controllers\Admin;

use LyBlog\Core\Request;
use LyBlog\Core\Session;
use LyBlog\Core\Validator;
use LyBlog\Models\Link;

class LinkController extends BaseAdminController
{
    public function index(Request $request)
    {
        $links = Link::all('sort_order ASC');
        $this->adminHeader('友情链接', 'links');
        $this->pageHeader('友情链接', '<a href="' . $this->adminUrl('links/create') . '" class="btn btn-primary">添加链接</a> <a href="' . $this->adminUrl('links/check-dead') . '" class="btn btn-secondary">检测死链</a>');
        $this->flashMessages();

        $headers = ['ID', '名称', 'URL', '描述', '排序', '状态', '操作'];
        $rows = [];
        foreach ($links as $l) {
            $rows[] = [
                $l['id'],
                htmlspecialchars($l['name']),
                '<a href="' . htmlspecialchars($l['url']) . '" target="_blank">' . htmlspecialchars($l['url']) . '</a>',
                htmlspecialchars($l['description'] ?? '-'),
                $l['sort_order'],
                $this->statusBadge($l['status'] ? 'active' : 'disabled'),
                $this->actionButtons($this->adminUrl('links/' . $l['id'] . '/edit'), $this->adminUrl('links/' . $l['id'] . '/delete')),
            ];
        }
        $this->table($headers, $rows, '暂无友链');
        $this->adminFooter();
    }

    public function create(Request $request)
    {
        $this->adminHeader('添加链接', 'links');
        $this->pageHeader('添加链接', '<a href="' . $this->adminUrl('links') . '" class="btn btn-secondary">← 返回</a>');
        $this->flashMessages();
        $this->renderForm();
        $this->adminFooter();
    }

    public function store(Request $request)
    {
        if (!Session::validateCsrf()) { Session::flash('error', '安全令牌无效'); $this->redirect($this->adminUrl('links/create')); return; }
        $data = $request->only(['name', 'url', 'description', 'logo', 'sort_order', 'status']);
        $v = Validator::quick($data, ['name' => 'required', 'url' => 'required|url'], ['name' => '名称', 'url' => 'URL']);
        if ($v->fails()) { Session::flash('error', $v->first()); $this->redirect($this->adminUrl('links/create')); return; }
        Link::create($data);
        Session::flash('success', '链接添加成功');
        $this->redirect($this->adminUrl('links'));
    }

    public function edit(Request $request, $id)
    {
        $link = Link::find((int) $id);
        if (!$link) { $this->notFound(); return; }
        $this->adminHeader('编辑链接', 'links');
        $this->pageHeader('编辑: ' . htmlspecialchars($link['name']), '<a href="' . $this->adminUrl('links') . '" class="btn btn-secondary">← 返回</a>');
        $this->flashMessages();
        $this->renderForm($link);
        $this->adminFooter();
    }

    public function update(Request $request, $id)
    {
        if (!Session::validateCsrf()) { Session::flash('error', '安全令牌无效'); $this->redirect($this->adminUrl('links/' . $id . '/edit')); return; }
        $data = $request->only(['name', 'url', 'description', 'logo', 'sort_order', 'status']);
        Link::update((int) $id, $data);
        Session::flash('success', '更新成功');
        $this->redirect($this->adminUrl('links'));
    }

    public function delete(Request $request, $id)
    {
        Link::delete((int) $id);
        Session::flash('success', '链接已删除');
        $this->redirect($this->adminUrl('links'));
    }

    public function checkDead(Request $request)
    {
        $links = Link::all();
        $dead = 0;
        foreach ($links as $link) {
            $headers = @get_headers($link['url'], 1);
            if (!$headers || strpos($headers[0], '200') === false && strpos($headers[0], '301') === false && strpos($headers[0], '302') === false) {
                Link::update($link['id'], ['status' => 0]);
                $dead++;
            }
        }
        Session::flash('success', "检测完成，{$dead} 个失效链接已自动隐藏");
        $this->redirect($this->adminUrl('links'));
    }

    private function renderForm(array $link = null): void
    {
        $isEdit = $link !== null;
        $action = $isEdit ? $this->adminUrl('links/' . $link['id'] . '/update') : $this->adminUrl('links');
        echo '<div class="card"><form method="post" action="' . $action . '">';
        echo Session::csrfField();
        if ($isEdit) echo '<input type="hidden" name="_method" value="PUT">';

        echo '<div class="form-row"><div class="form-group"><label>名称 *</label><input type="text" name="name" value="' . htmlspecialchars($link['name'] ?? '') . '" required></div>';
        echo '<div class="form-group"><label>URL *</label><input type="url" name="url" value="' . htmlspecialchars($link['url'] ?? '') . '" required></div></div>';
        echo '<div class="form-group"><label>描述</label><input type="text" name="description" value="' . htmlspecialchars($link['description'] ?? '') . '"></div>';
        echo '<div class="form-row"><div class="form-group"><label>Logo URL</label><input type="url" name="logo" value="' . htmlspecialchars($link['logo'] ?? '') . '"></div>';
        echo '<div class="form-group"><label>排序</label><input type="number" name="sort_order" value="' . (int) ($link['sort_order'] ?? 0) . '"></div></div>';
        echo '<div class="form-group"><label>状态</label><select name="status"><option value="1" ' . (($link['status'] ?? 1) == 1 ? 'selected' : '') . '>显示</option><option value="0" ' . (($link['status'] ?? '') === '0' ? 'selected' : '') . '>隐藏</option></select></div>';
        echo '<button type="submit" class="btn btn-primary">' . ($isEdit ? '更新' : '添加') . '</button>';
        echo '</form></div>';
    }
}
