<?php

declare(strict_types=1);

namespace Cenusis\Rpc;

use ReflectionFunction;
use ReflectionMethod;
use RuntimeException;

/**
 * enders-sync wire-protocol clone.
 *
 * - POST {path}/call with {method, params[]} -> {success, data?, error?}
 *   HTTP 200 even for RPC errors; handler exceptions are caught and
 *   returned as {success:false, error: message}.
 * - Handler signature: fn(Metadata $metadata, ...$params).
 */
final class Rpc
{
    /** @var array<string, callable> */
    private array $functions = [];

    /** @var callable(array $req): array{success: bool, metadata?: array} */
    private $validator;

    /**
     * @param callable(array $req): array{success: bool, metadata?: array}|null $validator
     */
    public function __construct(?callable $validator = null)
    {
        $this->validator = $validator ?? static fn (): array => ['success' => true];
    }

    /**
     * @param callable(array $req): array{success: bool, metadata?: array} $validator
     */
    public function setValidator(callable $validator): void
    {
        $this->validator = $validator;
    }

    public function add(callable $fn, ?string $name = null): void
    {
        $funcName = $name ?? self::callableName($fn);

        if (!$funcName) {
            throw new RuntimeException('Function must have a name');
        }

        $this->functions[$funcName] = $fn;
    }

    /** @return array<int, string> registered function names */
    public function dump(): array
    {
        return array_keys($this->functions);
    }

    /**
     * @param array<string, mixed> $requestData decoded {method, params}
     * @param array<string, mixed> $req raw request data (cookies, method, path, ...)
     */
    public function handler(array $requestData, array $req, Response $res): array
    {
        $methodName = $requestData['method'] ?? null;
        $params = $requestData['params'] ?? null;

        if (empty($methodName)) {
            return [
                'success' => false,
                'error' => 'bad request: the request need to have "method" and "params"',
            ];
        }

        if (!is_string($methodName)) {
            return [
                'success' => false,
                'error' => "bad request: RPC function doesn't exist",
            ];
        }

        if ($params !== null && !is_array($params)) {
            return [
                'success' => false,
                'error' => 'bad request: RPC params should be a list',
            ];
        }

        $functionHandler = $this->functions[$methodName] ?? null;
        if ($functionHandler === null) {
            return [
                'success' => false,
                'error' => "RPC function '{$methodName}' not found",
            ];
        }

        // running auth validator
        $validation = ($this->validator)($req);
        if (($validation['success'] ?? false) === false) {
            return [
                'success' => false,
                'error' => 'Authentication failed',
            ];
        }

        // passing request data into metadata
        $metadataArr = $validation['metadata'] ?? ['auth' => []];
        $metadata = new Metadata(
            $metadataArr['auth'] ?? [],
            $req,
            $res
        );

        // RPC call
        try {
            $result = $functionHandler($metadata, ...($params ?: []));
            return [
                'success' => true,
                'data' => $result,
            ];
        } catch (\Throwable $error) {
            error_log((string)$error);
            return [
                'success' => false,
                'error' => $error->getMessage(),
            ];
        }
    }

    private static function callableName(callable $fn): ?string
    {
        if (is_string($fn)) {
            return $fn;
        }

        if (is_array($fn)) {
            return is_string($fn[1] ?? null) ? $fn[1] : null;
        }

        try {
            if ($fn instanceof \Closure) {
                $name = (new ReflectionFunction($fn))->getName();
                return str_starts_with($name, '{closure') ? null : $name;
            }
            return (new ReflectionMethod($fn, '__invoke'))->getName();
        } catch (\ReflectionException) {
            return null;
        }
    }
}
