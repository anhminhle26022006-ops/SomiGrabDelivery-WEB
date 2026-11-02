<?php
declare(strict_types=1);

final class ApiProblem extends RuntimeException
{
    public function __construct(public readonly int $status, string $message, public readonly string $errorCode = 'REQUEST_FAILED')
    {
        parent::__construct($message);
    }
}
