<?php
declare(strict_types=1);

namespace Nivc\Core;

/** Thrown anywhere to produce an HTTP error response. */
final class HttpException extends \RuntimeException
{
    public function __construct(public readonly int $status, string $message, public readonly string $errorCode = 'error', public readonly array $extra = [])
    {
        parent::__construct($message);
    }

    public static function notFound(string $msg = 'Not found'): self
    {
        return new self(404, $msg, 'not_found');
    }

    public static function forbidden(string $msg = 'Forbidden'): self
    {
        return new self(403, $msg, 'forbidden');
    }

    public static function unauthorized(string $msg = 'Authentication required'): self
    {
        return new self(401, $msg, 'unauthenticated');
    }

    /** @param array<string,string> $fields field => message */
    public static function invalid(array $fields, string $msg = 'Validation failed'): self
    {
        return new self(422, $msg, 'validation_failed', ['fields' => $fields]);
    }
}
