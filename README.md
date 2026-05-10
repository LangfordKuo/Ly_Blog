# LyBlog

轻量化、插件化、模块化 PHP 博客系统

## 技术栈

- **PHP** 7.4+
- **MySQL** 5.7+ (utf8mb4)
- **设计风格** Soft Minimalism + Glassmorphism
- **零外部依赖** (Twig 语法兼容的自建模板引擎)
- **面向对象** PSR-4 风格自动加载

## 快速开始

### 安装

1. 将项目部署到 Web 服务器（Apache/Nginx）
2. 设置 `public/` 为 Web 根目录
3. 确保 `config/` 和 `storage/` 目录可写
4. 访问网站，自动跳转到安装向导
5. 按提示输入数据库信息和管理员账户
6. 安装完成后，访问 `/admin` 进入后台

### 伪静态规则

**Apache** — `public/.htaccess` 已包含

**Nginx** — 参考 `public/nginx.conf`

## 目录结构

```
Ly_Blog/
├── app/                          # 应用核心
│   ├── Core/                     # 框架内核
│   │   ├── Application.php       # 应用生命周期
│   │   ├── Auth.php              # 认证辅助
│   │   ├── Autoloader.php        # PSR-4 自动加载
│   │   ├── Config.php            # 配置读写
│   │   ├── Database.php          # PDO 封装
│   │   ├── Hook.php              # 钩子系统
│   │   ├── Logger.php            # 日志系统
│   │   ├── Plugin.php            # 插件管理器
│   │   ├── Request.php           # HTTP 请求封装
│   │   ├── Router.php            # URL 路由
│   │   ├── Sanitizer.php         # 输入净化
│   │   ├── Session.php           # Session 管理
│   │   ├── Validator.php         # 输入验证
│   │   └── View.php              # 模板引擎
│   ├── Controllers/              # 控制器
│   │   ├── BaseController.php
│   │   ├── Admin/                # 后台（14 个控制器）
│   │   └── Front/                # 前台（10 个控制器）
│   ├── Helpers/                  # 工具类
│   │   └── Str.php               # 字符串处理
│   └── Models/                   # 数据模型（8 个）
├── config/                       # 配置文件（安装时生成）
├── plugins/                      # 插件目录
│   └── sitemap/                  # Sitemap 插件
├── themes/                       # 主题目录
│   └── Default/                  # 默认主题
│       ├── theme.json
│       ├── templates/            # 模板文件（9 个）
│       └── assets/css/           # 样式
├── public/                       # Web 根目录
│   ├── index.php                 # 单入口
│   ├── install.php               # 安装向导
│   ├── .htaccess                 # Apache 伪静态
│   └── nginx.conf                # Nginx 伪静态
├── storage/                      # 可写数据
│   ├── cache/                    # 缓存
│   ├── logs/                     # 日志
│   ├── uploads/                  # 上传文件
│   └── backups/                  # 数据库备份
└── tests/                        # 测试
```

## 功能特性

### 核心功能

| 功能 | 说明 |
|------|------|
| 文章系统 | 发布/草稿/定时发布/私密，Markdown 编辑，自动摘要，封面图 |
| 分类系统 | 无限层级分类，树形结构 |
| 标签系统 | 标签云，关联文章计数 |
| 独立页面 | 关于我、友链等独立页面管理 |
| 评论系统 | 嵌套回复，审核机制（通过/待审/垃圾），防 XSS/SQL 注入 |
| 文章点赞 | AJAX 点赞/取消，IP 去重 |
| 搜索 | 全文搜索（标题+内容） |
| 归档 | 按年/月归档 |
| RSS | RSS 2.0 Feed |
| 友情链接 | 前/后台展示管理 |

### 用户与权限

| 角色 | 后台访问 | 权限说明 |
|------|---------|----------|
| 超级管理员 | ✅ | 全部权限 |
| 管理员 | ✅ | 全部权限 |
| 编辑 | ✅ | 文章/页面/评论/媒体管理 |
| 作者 | ✅ | 管理自己的文章 + 上传 |
| 订阅者 | ❌ | 仅前端评论 |

支持 21 种细粒度权限（`*` 通配符、`模块.*` 子权限匹配）。

### 插件系统

WordPress 风格 Hook 系统 (`add_action` / `do_action` / `add_filter` / `apply_filters`)，支持优先级排序。

**内置插件：**
- **Sitemap** — 自动生成 `sitemap.xml`

**添加插件：**
1. 在 `plugins/` 下创建目录（如 `my-plugin/`）
2. 创建 `plugin.json` 清单文件
3. 创建主类文件，在构造函数中注册 Hook
4. 后台 → 插件管理 → 启用

### 主题系统

- 支持多主题切换
- 后台一键启用
- 主题目录含 `theme.json` 元信息
- 默认主题：Soft Minimalism + Glassmorphism

## 模板语法

模板引擎兼容 Twig 语法：

```
{% extends "layout.twig" %}          # 继承布局
{% block content %}...{% endblock %}  # 内容块

{% if condition %}...{% endif %}     # 条件判断
{% for item in items %}...{% endfor %} # 循环

{{ variable }}                        # 输出（自动转义）
{{ content|raw }}                     # 原始输出
{{ text|upper }}                      # 转大写
{{ text|escape }}                     # HTML 转义
{{ items|length }}                    # 长度
{{ text|nl2br }}                      # 换行转换
{{ data|json }}                       # JSON 编码
{{ value|default("默认") }}           # 默认值
{{ date|date('Y-m-d') }}              # 日期格式化
{{ text|strip_tags }}                 # 去除标签

{% include "file.twig" %}             # 包含子模板
{% include "file.twig" with {key: val} %} # 带参数包含
{% set var = value %}                 # 变量赋值
```

## API 参考

### Hook 系统

```php
Hook::addAction('init', $callback, 10);
Hook::doAction('init', $arg1, $arg2);

Hook::addFilter('content', $callback, 10);
$content = Hook::applyFilters('content', $value, $arg1);

Hook::removeAction('init', $callback);
```

### 数据库操作

```php
$db = Database::getInstance();

// 查询
$db->fetch("SELECT * FROM {users} WHERE id = ?", [$id]);
$db->fetchAll("SELECT * FROM {users} WHERE status = ?", [1]);
$db->fetchColumn("SELECT COUNT(*) FROM {users}");

// 增删改
$id = $db->insert('users', ['username' => 'foo']);
$db->update('users', ['status' => 0], 'id = ?', [1]);
$db->delete('users', 'id = ?', [1]);

// 事务
$db->beginTransaction();
$db->commit();
$db->rollback();
```

### 验证器

```php
$v = Validator::quick($_POST, [
    'name' => 'required|min:2|max:50',
    'email' => 'required|email',
    'password' => 'required|min:6',
], ['name' => '姓名', 'email' => '邮箱']);

if ($v->fails()) {
    $errors = $v->errors();
    $first = $v->first();
}
```

## 安全

- Bcrypt 密码哈希 (cost=12)
- CSRF Token 所有表单
- XSS 过滤（HTML 净化器）
- SQL 注入防护（PDO 参数绑定）
- 登录限流（IP 15分钟 5 次）
- Session 固定攻击防护
- Session Cookie HttpOnly + SameSite Lax

## License

MIT
