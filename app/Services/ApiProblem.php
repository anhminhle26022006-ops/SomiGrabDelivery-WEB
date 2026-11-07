<?php
declare(strict_types=1);

final class ApiProblem extends RuntimeException
{
    public function __construct(public int $status, string $message, public string $errorCode = 'REQUEST_FAILED')
    {
        parent::__construct($message);
    }
}
