<?php
/**
 * Phase 2 Tests: Router, Hook, Session, Sanitizer, Validator, Logger
 */

require __DIR__ . '/../app/Core/Autoloader.php';
$autoloader = new \LyBlog\Core\Autoloader('LyBlog\\', __DIR__ . '/../app');
$autoloader->register();

define('ROOT_DIR', dirname(__DIR__));
define('STORAGE_DIR', ROOT_DIR . '/storage');

use LyBlog\Core\Request;
use LyBlog\Core\Router;
use LyBlog\Core\Hook;
use LyBlog\Core\Session;
use LyBlog\Core\Sanitizer;
use LyBlog\Core\Validator;
use LyBlog\Core\Logger;

$pass = 0;
$fail = 0;

function check(string $name, $result, $expected = true) {
    global $pass, $fail;
    if ($expected === true) {
        if ($result) { echo "  PASS: $name\n"; $pass++; }
        else { echo "  FAIL: $name\n"; $fail++; }
    } else {
        if ($result === $expected) { echo "  PASS: $name\n"; $pass++; }
        else { echo "  FAIL: $name (got: " . json_encode($result) . ", expected: " . json_encode($expected) . ")\n"; $fail++; }
    }
}

// ─── 1. Sanitizer ─────────────────────────
echo "▶ Sanitizer\n";
check('text', Sanitizer::text('<script>alert("x")</script>') === '&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;');
check('html strips scripts', strpos(Sanitizer::html('<p>Hi</p><script>evil()</script>'), '<script>') === false);
check('html keeps safe', strpos(Sanitizer::html('<p>Hi</p>'), '<p>Hi</p>') !== false);
check('html removes onclick', strpos(Sanitizer::html('<a onclick="xss">link</a>'), 'onclick') === false);
check('int', Sanitizer::int('123abc') === 123);
check('float', Sanitizer::float('3.14') === 3.14);
check('email', Sanitizer::email('Test@Example.com ') === 'Test@Example.com');
check('slug', Sanitizer::slug('Hello World!') === 'hello-world');
check('filename', Sanitizer::filename('my file (1).jpg') === 'my-file-1-.jpg');
check('alphanumeric', Sanitizer::alphanumeric('abc123!@#') === 'abc123');
check('clean array', Sanitizer::clean(['<b>', ['<x>']]) === ['&lt;b&gt;', ['&lt;x&gt;']]);

// ─── 2. Validator ─────────────────────────
echo "\n▶ Validator\n";
$v = Validator::quick(['name' => 'John', 'email' => 'bad', 'age' => 'abc', 'bio' => ''], [
    'name'  => 'required|min:2|max:50',
    'email' => 'required|email',
    'age'   => 'integer',
    'bio'   => 'max:200',
], ['name' => '姓名', 'email' => '邮箱', 'age' => '年龄']);

check('name valid', !$v->hasError('name'));
check('email invalid', $v->hasError('email'));
check('age invalid', $v->hasError('age'));
check('bio valid (empty allowed)', !$v->hasError('bio'));
check('fails', $v->fails());

$v2 = Validator::quick(['pass' => 'abc', 'confirm' => 'abd'], [
    'pass' => 'required',
    'confirm' => 'match:pass',
]);
check('match fail', $v2->hasError('confirm'));

$v3 = Validator::quick(['status' => 'active'], ['status' => 'in:active,inactive']);
check('in pass', $v3->passes());

$v4 = Validator::quick(['status' => 'deleted'], ['status' => 'in:active,inactive']);
check('in fail', $v4->fails());

$v5 = Validator::quick(['ip' => '192.168.1.1'], ['ip' => 'ip']);
check('ip pass', $v5->passes());

$v6 = Validator::quick(['ip' => '999.999.999.999'], ['ip' => 'ip']);
check('ip fail', $v6->fails());

$v7 = Validator::quick(['data' => '{"a":1}'], ['data' => 'json']);
check('json pass', $v7->passes());

$v8 = Validator::quick(['data' => 'not-json'], ['data' => 'json']);
check('json fail', $v8->fails());

// ─── 3. Logger ────────────────────────────
echo "\n▶ Logger\n";
$logger = Logger::init(STORAGE_DIR . '/logs', 'debug');
check('logger instance', $logger !== null);
$logger->debug('Test debug message', ['key' => 'val']);
$logger->info('Test info message');
$logger->warning('Test warning message');
$logger->error('Test error message');
$logFile = STORAGE_DIR . '/logs/' . date('Y-m-d') . '.log';
check('log file exists', file_exists($logFile));
$logContent = file_get_contents($logFile);
check('log has debug', strpos($logContent, 'DEBUG') !== false);
check('log has info', strpos($logContent, 'INFO') !== false);
check('log has error', strpos($logContent, 'ERROR') !== false);

// Test log level filtering
$logger->setLevel('error');
$logger->debug('Should not appear');
$logger->error('Should appear');
$logContent2 = file_get_contents($logFile);
check('level filter works', strpos($logContent2, 'Should appear') !== false);
$logger->clear();

// ─── 4. Hook System ───────────────────────
echo "\n▶ Hook System\n";
$hookResult = '';
Hook::addAction('test_hook', function ($msg) use (&$hookResult) {
    $hookResult = $msg;
});
Hook::doAction('test_hook', 'Hello Hook');
check('doAction', $hookResult === 'Hello Hook');

$filterResult = '';
Hook::addFilter('test_filter', function ($value, $append) {
    return $value . ' ' . $append;
}, 10);
$result = Hook::applyFilters('test_filter', 'Hello', 'World');
check('applyFilters', $result === 'Hello World');

check('hasAction', Hook::hasAction('test_hook'));
check('hasFilter', Hook::hasFilter('test_filter'));
check('didAction', Hook::didAction('test_hook'));

Hook::removeAction('test_hook');
check('removeAction', !Hook::hasAction('test_hook'));

Hook::removeFilter('test_filter');
check('removeFilter', !Hook::hasFilter('test_filter'));

// Priority test
$order = [];
Hook::addAction('priority_test', function () use (&$order) { $order[] = 1; }, 20);
Hook::addAction('priority_test', function () use (&$order) { $order[] = 2; }, 10);
Hook::doAction('priority_test');
check('priority order', $order === [2, 1]);

// ─── 5. Session ───────────────────────────
echo "\n▶ Session\n";
@session_start();
check('flash set', Session::flash('msg', 'Test') === null);
check('flash has', Session::hasFlash('msg'));
check('flash get', Session::flash('msg') === 'Test');
check('flash gone', !Session::hasFlash('msg'));

$token = Session::csrfToken();
check('csrf token', strlen($token) === 64);
check('csrf field', strpos(Session::csrfField(), 'csrf_token') !== false);

// Simulate CSRF validation
$_POST['_csrf_token'] = $token;
check('csrf validate', Session::validateCsrf());
unset($_POST['_csrf_token']);
$_POST['_csrf_token'] = 'bad';
check('csrf reject bad', !Session::validateCsrf());
unset($_POST['_csrf_token']);

// ─── 6. Router ────────────────────────────
echo "\n▶ Router\n";
$router = new Router();
$router->get('/', 'Front\HomeController@index');
$router->get('/article/{id}', 'Front\ArticleController@show');
$router->group('/admin', function (Router $r) {
    $r->get('/', 'Admin\DashboardController@index');
    $r->get('/articles', 'Admin\ArticleController@index');
    $r->post('/articles', 'Admin\ArticleController@store');
});

$routes = $router->getRoutes();

// Test pattern matching (regex verification)
$foundHome = false;
$foundArticle = false;
$foundAdmin = false;
foreach ($routes as $route) {
    if ($route['pattern'] === '/' && $route['method'] === 'GET') $foundHome = true;
    if ($route['pattern'] === '/article/{id}') $foundArticle = true;
    if ($route['pattern'] === '/admin' && $route['method'] === 'GET') $foundAdmin = true;
}
check('home route', $foundHome);
check('article route', $foundArticle);
check('admin route', $foundAdmin);
check('routes count', count($routes) === 5);

// ─── 7. Request ───────────────────────────
echo "\n▶ Request\n";
// Can't fully simulate superglobals, but test basic construction
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/article/123';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['HTTP_HOST'] = 'localhost';
$_GET = ['page' => '1'];
$_POST = [];

$req = new Request();
check('method', $req->getMethod() === 'GET');
check('path', $req->getPath() === '/article/123');
check('segments', $req->getSegments() === ['article', '123']);
check('segment 0', $req->segment(0) === 'article');
check('segment 1', $req->segment(1) === '123');
check('query param', $req->getQuery('page') === '1');
check('isGet', $req->isGet());
check('isPost false', !$req->isPost());

echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Results: $pass passed, $fail failed\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━\n";
exit($fail > 0 ? 1 : 0);
