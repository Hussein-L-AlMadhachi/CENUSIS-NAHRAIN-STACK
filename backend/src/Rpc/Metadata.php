<?php

declare(strict_types=1);

namespace Cenusis\Rpc;

/**
 * Per-request data passed to handlers as the first argument,
 * mirroring enders-sync Metadata { auth, req, res }.
 */
final class Metadata
{
    /** @var array<string, mixed> auth claims { user_id, role } (empty for public) */
    public array $auth;

    /** @var array<string, mixed> raw request data */
    public array $req;

    public Response $res;

    public function __construct(array $auth = [], array $req = [], ?Response $res = null)
    {
        $this->auth = $auth;
        $this->req = $req;
        $this->res = $res ?? new Response();
    }
}
