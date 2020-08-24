<?php

declare(strict_types=1);

namespace MakiseCo\EvPrimitives;

use LogicException;
use RuntimeException;
use Swoole\Coroutine\Channel;
use Throwable;

use const SWOOLE_CHANNEL_TIMEOUT;

/**
 * Reusable Deferred implementation
 * Instead of usual deferred it can be reused
 *
 * @see examples/Reusable/concurrency.php
 * @see examples/Reusable/waiting.php
 *
 */
class ReusableDeferred implements PromiseInterface
{
    /**
     * Resolution results store
     */
    private Channel $resolveChannel;

    public function __construct()
    {
        $this->resolveChannel = new Channel();
    }

    public function __destruct()
    {
        $this->resolveChannel->close();
    }

    public function isWaiting(): bool
    {
        return ($this->resolveChannel->stats()['consumer_num'] ?? 0) > 0;
    }

    /**
     * This method will not work because this is reusable promise
     */
    public function isResolved(): bool
    {
        return false;
    }

    /**
     * @param float $timeout maximum time to wait result
     *
     * @return mixed when resolution successful
     *
     * @throws Throwable when resolution fails
     */
    public function wait(float $timeout = 0)
    {
        $result = $this->resolveChannel->pop($timeout);

        if (!$result instanceof DeferredResult) {
            if ($this->resolveChannel->errCode === SWOOLE_CHANNEL_TIMEOUT) {
                throw new RuntimeException('wait timeout');
            }

            throw new LogicException('Deferred is closed');
        }

        return $result->unwrap();
    }

    public function resolve($value): void
    {
        $result = new DeferredResult();
        $result->resolve($value);

        $this->resolveChannel->push($result);
    }

    public function fail(Throwable $reason): void
    {
        $result = new DeferredResult();
        $result->fail($reason);

        $this->resolveChannel->push($result);
    }
}
