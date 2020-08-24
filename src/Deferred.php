<?php

declare(strict_types=1);

namespace MakiseCo\EvPrimitives;

use LogicException;
use SplQueue;
use Swoole\Coroutine;
use Throwable;

/**
 * AMPHP Deferred port
 */
class Deferred implements PromiseInterface
{
    private ?DeferredResult $result = null;
    private SplQueue $queue;

    public function __construct()
    {
        $this->queue = new SplQueue();
    }

    public function isResolved(): bool
    {
        return $this->result !== null;
    }

    public function isWaiting(): bool
    {
        return !$this->queue->isEmpty();
    }

    public function wait(float $timeout = 0)
    {
        // return resolved result
        if ($this->result !== null) {
            return $this->result->unwrap();
        }

        $this->queue->enqueue(Coroutine::getCid());

        Coroutine::yield();

        if ($this->result === null) {
            throw new LogicException('No result in promise');
        }

        return $this->result->unwrap();
    }

    public function resolve($value): void
    {
        if ($this->result !== null) {
            return;
        }

        $result = new DeferredResult();
        $result->resolve($value);

        $this->resume($result);
    }

    public function fail(Throwable $fail): void
    {
        if ($this->result !== null) {
            return;
        }

        $result = new DeferredResult();
        $result->fail($fail);

        $this->resume($result);
    }

    private function resume(DeferredResult $result): void
    {
        $this->result = $result;

        while (!$this->queue->isEmpty()) {
            Coroutine::resume($this->queue->dequeue());
        }
    }
}
