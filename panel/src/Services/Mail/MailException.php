<?php
declare(strict_types=1);

namespace Nivc\Services\Mail;

/** A mail-protocol failure. The code is a stable key (translated as mail.err.<code>); the message is the server's text. */
final class MailException extends \RuntimeException
{
    public function __construct(public readonly string $errorCode, string $message = '')
    {
        parent::__construct($message);
    }
}
