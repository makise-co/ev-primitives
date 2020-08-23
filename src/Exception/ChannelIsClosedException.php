<?php

declare(strict_types=1);

namespace MakiseCo\EvPrimitives\Exception;

use Throwable;

class ChannelIsClosedException extends ChannelException
{
    public function __construct($message = 'Channel is closed', $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
