<?php
declare(strict_types=1);

final class DeliveryApiError extends RuntimeException
{
    public int $httpStatus;
    public string $errorCode;

    public function __construct(int $status, string $code, string $message)
    {
        parent::__construct($message);
        $this->httpStatus = $status;
        $this->errorCode = $code;
    }
}
