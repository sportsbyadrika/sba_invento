<?php
declare(strict_types=1);

namespace App\Core;

class HttpException extends \RuntimeException
{
    public function __construct(public readonly int $status, string $message = '')
    {
        parent::__construct($message ?: match ($status) {
            403 => 'You do not have permission to access this page.',
            404 => 'The page you are looking for was not found.',
            419 => 'Your session has expired. Please refresh the page and try again.',
            429 => 'Too many requests. Please wait and try again.',
            default => 'Something went wrong.',
        }, $status);
    }
}
