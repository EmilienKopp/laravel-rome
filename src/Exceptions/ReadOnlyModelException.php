<?php

namespace Splitstack\Rome\Exceptions;

class ReadOnlyModelException extends \BadMethodCallException
{
    public function __construct($message = 'This model is read-only and cannot be modified.', $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
