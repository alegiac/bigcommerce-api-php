<?php

namespace Bigcommerce\Api\Exceptions;

class ServerException extends \Exception
{
    public function __construct(string $message, int $code = 0, \Throwable $previous = null)
    {
        parent::__construct("BigCommerce Server Exception: ". $message, $code, $previous);
    }
}