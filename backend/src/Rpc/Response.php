<?php

declare(strict_types=1);

namespace Cenusis\Rpc;

/**
 * Minimal response helper given to handlers, mirroring the express
 * res object surface used by the TS backend (cookies + sending output).
 */
final class Response
{
    private int $status = 200;
    private bool $sent = false;

    /** @var array<int, array{name: string, value: string, options: array<string, mixed>}> */
    private array $cookies = [];

    public function status(int $code): self
    {
        $this->status = $code;
        return $this;
    }

    /**
     * Queue a cookie. Options (express-style):
     *   maxAge (seconds), httpOnly (bool), secure (bool),
     *   sameSite ('lax'|'strict'|'none'), path, domain.
     * Cookies are actually emitted when the response body is sent.
     */
    public function setCookie(string $name, string $value = '', array $opts = []): void
    {
        $this->cookies[] = [
            'name' => $name,
            'value' => $value,
            'options' => $opts,
        ];
    }

    /** Read a cookie from the incoming request. */
    public function getCookie(string $name): ?string
    {
        return isset($_COOKIE[$name]) ? (string)$_COOKIE[$name] : null;
    }

    public function json(mixed $data): void
    {
        $this->send(json_encode($data) ?: '', 'application/json');
    }

    public function send(string $body, string $contentType = 'text/html'): void
    {
        if ($this->sent) {
            return;
        }
        $this->sent = true;

        $this->flushCookies();

        http_response_code($this->status);
        header('Content-Type: ' . $contentType);
        echo $body;
    }

    public function isSent(): bool
    {
        return $this->sent;
    }

    public function flushCookies(): void
    {
        foreach ($this->cookies as $cookie) {
            $opts = $cookie['options'];
            $maxAge = isset($opts['maxAge']) ? (int)$opts['maxAge'] : 0;

            $phpOpts = [
                'expires' => $maxAge > 0 ? time() + $maxAge : 0,
                'path' => $opts['path'] ?? '/',
                'secure' => (bool)($opts['secure'] ?? false),
                'httponly' => (bool)($opts['httpOnly'] ?? false),
                'samesite' => ucfirst(strtolower((string)($opts['sameSite'] ?? 'Lax'))),
            ];
            if (isset($opts['domain'])) {
                $phpOpts['domain'] = $opts['domain'];
            }

            setcookie($cookie['name'], $cookie['value'], $phpOpts);
        }
        $this->cookies = [];
    }
}
