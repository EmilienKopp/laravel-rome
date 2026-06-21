<?php

namespace Splitstack\Rome\Exceptions;

class ProxiedModelException extends \LogicException
{
    public function __construct($message = 'Proxying is disabled or misconfigured for this model.', $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
