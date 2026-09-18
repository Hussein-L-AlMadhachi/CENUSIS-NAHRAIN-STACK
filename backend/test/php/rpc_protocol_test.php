<?php

declare(strict_types=1);

// CLI test for the RPC scaffold: run with `php test/php/rpc_protocol_test.php`
// from the backend/ directory. Covers wire-protocol behavior, JWT and helpers.
// Database-dependent RPCs (login/getAccountInfo) need a live MySQL and are not covered here.

use Cenusis\Auth\Auth;
use Cenusis\Auth\Jwt;
use Cenusis\Rpc\Metadata;
use Cenusis\Rpc\Response;
use Cenusis\Rpc\Rpc;
use function Cenusis\Helpers\prepareFtsPrefixQuery;
use function Cenusis\Helpers\translateHeaders;
use function Cenusis\Helpers\normalize_arabic;
use function Cenusis\Helpers\loose_validate_params;
use function Cenusis\Helpers\validate_params;

require __DIR__ . '/../../src/helpers/helpers.php';
spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'Cenusis\\')) {
        return;
    }
    $file = __DIR__ . '/../../src/' . str_replace('\\', '/', substr($class, strlen('Cenusis\\'))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

$failures = 0;

function check(string $name, bool $condition): void
{
    global $failures;
    if ($condition) {
        echo "PASS {$name}\n";
    } else {
        $failures++;
        echo "FAIL {$name}\n";
    }
}

function call_rpc(Rpc $rpc, array $body, array $cookies = []): array
{
    $req = ['method' => 'POST', 'path' => '/api/test/call', 'query' => [], 'cookies' => $cookies, 'body' => $body];
    return $rpc->handler($body, $req, new Response());
}

// --- JWT roundtrip ---
$secret = 'unit-test-secret';
putenv("JWT_SECRET={$secret}");
$token = Jwt::encode(['role' => 'admin', 'user_id' => 7], $secret, 86400, 'admin-auth-service', 'admin');
$parts = explode('.', $token);
check('jwt: 3 segments', count($parts) === 3);
check('jwt: header alg HS256', json_decode(Jwt::b64urlDecode($parts[0]), true)['alg'] === 'HS256');

$payload = Jwt::decode($token, $secret);
check('jwt: decode roundtrip', is_array($payload)
    && $payload['role'] === 'admin'
    && $payload['user_id'] === 7
    && $payload['iss'] === 'admin-auth-service'
    && $payload['aud'] === 'admin');
check('jwt: exp in future', is_array($payload) && $payload['exp'] > time());

check('jwt: wrong secret rejected', Jwt::decode($token, 'other-secret') === null);
check('jwt: garbage rejected', Jwt::decode('not.a.jwt', $secret) === null);

// tampered payload
$tampered = $parts[0] . '.' . Jwt::b64urlEncode((string)json_encode(['role' => 'superadmin', 'user_id' => 7])) . '.' . $parts[2];
check('jwt: tampered payload rejected', Jwt::decode($tampered, $secret) === null);

// expired token (ttl 1s, then wait for it to lapse)
$expired = Jwt::encode(['role' => 'admin', 'user_id' => 7], $secret, 1);
sleep(2);
check('jwt: expired token rejected', Jwt::decode($expired, $secret) === null);

// --- Helpers ---
check('normalize_arabic: أ->ا', normalize_arabic('أحمد') === 'احمد');
check('normalize_arabic: ة->ه ئ->ي ء->ا', normalize_arabic('ةئء') === 'هيا');
check('normalize_arabic: latin passthrough', normalize_arabic('admin 1') === 'admin 1');
check('normalize_arabic: null->empty', normalize_arabic(null) === '');

$caught = null;
try {
    validate_params(['a' => 1, 'zz' => 2], ['a']);
} catch (RuntimeException $e) {
    $caught = $e->getMessage();
}
check('validate_params: unexpected key msg', $caught === 'invalid request: unexpected key zz');

$caught = null;
try {
    validate_params(['a' => 1], ['a', 'b']);
} catch (RuntimeException $e) {
    $caught = $e->getMessage();
}
check('validate_params: missing key msg', $caught === 'invalid request: missing key b');

loose_validate_params(['a' => 1, 'extra' => 2], ['a']);
check('loose_validate_params: allows extras', true);

check('fts: strips metachars + wildcard', prepareFtsPrefixQuery("o'brien(&|!<>)  x ") === 'o brien x:*');

$out = translateHeaders(
    [['name' => 1, 'class' => 2]],
    ['name' => 'student_name', 'class' => 'student_class']
);
check('translate_headers: maps keys', $out === [['student_name' => 1, 'student_class' => 2]]);

$caught = null;
try {
    translateHeaders([['unknown' => 1]], []);
} catch (RuntimeException $e) {
    $caught = $e->getMessage();
}
check('translate_headers: unexpected header msg', $caught === 'unexpected header unknown');

// --- RPC wire protocol ---
$rpc = new Rpc();
$rpc->add(static fn (Metadata $m, $a, $b) => $a + $b, 'sum');
$rpc->add(static function (Metadata $m): array {
    return ['echo' => $m->auth, 'cookie' => $m->res->getCookie('x')];
}, 'whoami');
$rpc->add(static fn (Metadata $m) => throw new RuntimeException('boom'), 'fails');

$_COOKIE = ['x' => 'cv'];

$r = call_rpc($rpc, []);
check('rpc: empty body bad request', $r === ['success' => false, 'error' => 'bad request: the request need to have "method" and "params"']);

$r = call_rpc($rpc, ['method' => '']);
check('rpc: empty method bad request', $r === ['success' => false, 'error' => 'bad request: the request need to have "method" and "params"']);

$r = call_rpc($rpc, ['method' => 123]);
check('rpc: non-string method', $r === ['success' => false, 'error' => "bad request: RPC function doesn't exist"]);

$r = call_rpc($rpc, ['method' => 'sum', 'params' => 'x']);
check('rpc: params not a list', $r === ['success' => false, 'error' => 'bad request: RPC params should be a list']);

$r = call_rpc($rpc, ['method' => 'nope', 'params' => []]);
check('rpc: unknown function msg', $r === ['success' => false, 'error' => "RPC function 'nope' not found"]);

$r = call_rpc($rpc, ['method' => 'sum', 'params' => [2, 3]]);
check('rpc: call ok (public)', $r === ['success' => true, 'data' => 5]);

// validator path
$secureRpc = new Rpc(Auth::generateAuthValidatorForRoles('admin'));
$secureRpc->add(static fn (Metadata $m) => $m->auth, 'whoami');

$r = call_rpc($secureRpc, ['method' => 'whoami', 'params' => []]);
check('rpc: auth failure message', $r === ['success' => false, 'error' => 'Authentication failed']);

// valid admin token
$adminToken = Jwt::encode(['user_id' => 3, 'role' => 'admin'], $secret, 86400);
$r = call_rpc($secureRpc, ['method' => 'whoami', 'params' => []], ['auth-token' => $adminToken]);
check('rpc: valid admin metadata', ($r['success'] ?? false) === true
    && ($r['data']['user_id'] ?? null) === 3
    && ($r['data']['role'] ?? '') === 'admin');

// wrong role token (teacher cannot call admin route)
$teacherToken = Jwt::encode(['user_id' => 3, 'role' => 'teacher'], $secret, 86400);
$_COOKIE = ['auth-token' => $teacherToken];
$r = call_rpc($secureRpc, ['method' => 'whoami', 'params' => []], ['auth-token' => $teacherToken]);
check('rpc: role mismatch -> Authentication failed', $r === ['success' => false, 'error' => 'Authentication failed']);

// handler exception is caught, HTTP-level error in body (registered on the public rpc)
$r = call_rpc($rpc, ['method' => 'fails', 'params' => []]);
check('rpc: handler exception captured', $r === ['success' => false, 'error' => 'boom']);

// add() without a name and without an inferable name must throw
$thrown = false;
try {
    (new Rpc())->add(static fn () => 1);
} catch (RuntimeException) {
    $thrown = true;
}
check('rpc: unnamed closure must throw', $thrown);

// dump()
$dumpRpc = new Rpc();
$dumpRpc->add(static fn () => null, 'login');
$dumpRpc->add(static fn () => null, 'logout');
check('rpc: dump returns names in order', $dumpRpc->dump() === ['login', 'logout']);

echo $failures === 0
    ? "\nAll tests passed.\n"
    : "\n{$failures} test(s) failed.\n";
exit($failures === 0 ? 0 : 1);
