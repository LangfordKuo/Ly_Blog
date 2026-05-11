<?php

namespace LyBlog\Core;

class Mailer
{
    private $host;
    private $port;
    private $username;
    private $password;
    private $encryption = 'tls'; // tls, ssl, none
    private $from;
    private $fromName;
    private $timeout = 15;
    private $socket;
    private $lastError = '';

    public function __construct(array $config = [])
    {
        $this->host     = $config['smtp_host'] ?? '';
        $this->port     = (int) ($config['smtp_port'] ?? 587);
        $this->username = $config['smtp_user'] ?? '';
        $this->password = $config['smtp_pass'] ?? '';
        $this->from     = $config['smtp_from'] ?? '';
        $this->fromName = Config::get('site_name', 'LyBlog');

        if (empty($this->from)) {
            $this->from = $this->username;
        }
    }

    public function send(string $to, string $subject, string $body, string $plainText = ''): bool
    {
        if (empty($this->host) || empty($this->username) || empty($this->password)) {
            $this->lastError = 'SMTP 配置不完整';
            return false;
        }

        try {
            $this->connect();
            $this->authenticate();

            $this->command('MAIL FROM: <' . $this->from . '>', 250);
            $this->command('RCPT TO: <' . $to . '>', 250);

            $this->command('DATA', 354);

            $headers = $this->buildHeaders($to, $subject);
            $message = $headers . "\r\n";
            $message .= ($plainText ?: strip_tags($body));

            if (!empty($body) && $body !== $plainText) {
                $boundary = '--=_LyBlog_' . md5(uniqid());
                $message = $headers . "\r\n";
                $message .= "MIME-Version: 1.0\r\n";
                $message .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n\r\n";
                $message .= "--{$boundary}\r\n";
                $message .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
                $message .= ($plainText ?: strip_tags($body)) . "\r\n\r\n";
                $message .= "--{$boundary}\r\n";
                $message .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
                $message .= $body . "\r\n\r\n";
                $message .= "--{$boundary}--";
            }

            $this->command($message . "\r\n.", 250);
            $this->command('QUIT', 221);

            $this->disconnect();
            return true;
        } catch (\Exception $e) {
            $this->lastError = $e->getMessage();
            $this->disconnect();
            return false;
        }
    }

    public function getLastError(): string
    {
        return $this->lastError;
    }

    private function buildHeaders(string $to, string $subject): string
    {
        return "From: {$this->fromName} <{$this->from}>\r\n"
            . "To: <{$to}>\r\n"
            . "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n"
            . "Date: " . date('r') . "\r\n"
            . "Message-ID: <" . md5(uniqid()) . "@{$this->host}>\r\n"
            . "X-Mailer: LyBlog Mailer\r\n";
    }

    private function connect(): void
    {
        $host = ($this->encryption === 'ssl') ? 'ssl://' . $this->host : $this->host;
        $this->socket = @fsockopen($host, $this->port, $errno, $errstr, $this->timeout);

        if (!$this->socket) {
            throw new \RuntimeException("SMTP 连接失败: {$errstr} ({$errno})");
        }

        $this->read(); // Read greeting

        $this->command('EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost'), 250);

        // Start TLS if needed
        if ($this->encryption === 'tls') {
            $this->command('STARTTLS', 220);
            if (!stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new \RuntimeException('TLS 加密启动失败');
            }
            $this->command('EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost'), 250);
        }
    }

    private function authenticate(): void
    {
        $this->command('AUTH LOGIN', 334);
        $this->command(base64_encode($this->username), 334);
        $this->command(base64_encode($this->password), 235);
    }

    private function command(string $cmd, int $expectedCode): string
    {
        fwrite($this->socket, $cmd . "\r\n");
        return $this->read($expectedCode);
    }

    private function read(int $expectedCode = 0): string
    {
        $response = '';
        while ($line = fgets($this->socket, 512)) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }

        if ($expectedCode > 0) {
            $code = (int) substr($response, 0, 3);
            if ($code !== $expectedCode) {
                throw new \RuntimeException("SMTP 错误 {$code}: " . trim($response));
            }
        }

        return $response;
    }

    private function disconnect(): void
    {
        if ($this->socket) {
            @fclose($this->socket);
            $this->socket = null;
        }
    }

    public static function fromConfig(): ?self
    {
        $host = Config::get('smtp_host');
        if (empty($host)) return null;

        return new self([
            'smtp_host' => $host,
            'smtp_port' => Config::get('smtp_port', 587),
            'smtp_user' => Config::get('smtp_user', ''),
            'smtp_pass' => Config::get('smtp_pass', ''),
            'smtp_from' => Config::get('smtp_from', ''),
        ]);
    }

    /**
     * Send a comment notification email.
     */
    public static function sendCommentNotification(array $comment, array $article): void
    {
        $siteName = Config::get('site_name', 'LyBlog');
        $siteUrl  = rtrim(Config::get('site_url', '/'), '/');
        $adminEmail = Config::get('smtp_from');

        if (empty($adminEmail)) return;

        $mailer = self::fromConfig();
        if ($mailer === null) return;

        $articleUrl = $siteUrl . '/article/' . $article['slug'];
        $subject = "[{$siteName}] 新评论: {$article['title']}";

        $body = '<div style="font-family:sans-serif;max-width:600px;margin:0 auto">';
        $body .= '<h2 style="color:#1d1d1f">新评论通知</h2>';
        $body .= '<p><strong>文章:</strong> <a href="' . htmlspecialchars($articleUrl) . '">' . htmlspecialchars($article['title']) . '</a></p>';
        $body .= '<p><strong>评论者:</strong> ' . htmlspecialchars($comment['author_name']) . '</p>';
        $body .= '<p><strong>邮箱:</strong> ' . htmlspecialchars($comment['author_email'] ?? '') . '</p>';
        $body .= '<div style="background:#f5f5f7;padding:16px;border-radius:8px;margin:12px 0">';
        $body .= nl2br(htmlspecialchars($comment['content']));
        $body .= '</div>';
        $body .= '<p><a href="' . htmlspecialchars($articleUrl . '#comments') . '" style="display:inline-block;padding:10px 20px;background:#1d1d1f;color:#fff;text-decoration:none;border-radius:8px">查看文章</a></p>';
        $body .= '</div>';

        $mailer->send($adminEmail, $subject, $body);
    }
}
