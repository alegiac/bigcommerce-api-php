<?php

namespace Bigcommerce\Api\Exceptions;

class ClientException extends \Exception
{
    public function __construct($message = "Bigcommerce connection error", $code = 0, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
