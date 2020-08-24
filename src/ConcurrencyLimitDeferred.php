<?php

declare(strict_types=1);

namespace MakiseCo\EvPrimitives;

use RuntimeException;
use SplQueue;
use Swoole\Coroutine;
use Swoole\Timer;
use Throwable;

/**
 * Concurrency Limit Deferred implementation
 *
 * Limiting access to resource that does not support concurrent calls
 *
 * Usage:
 *
 *  while ($deferred->isWaiting()) {
 *      $deferred->waitForResolution();
 *  }
 *
 *  // your code
 *
 * @see ConcurrencyLimitDeferred::waitForResolution
 * @see examples/ConcurrencyLimit/concurrency.php
 */
class ConcurrencyLimitDeferred implements PromiseInterface
{
    private ReusableDeferred $deferred;

    /**
     * Waiting coroutines list for waitForResolution
     */
    private SplQueue $waiters;

    /**
     * Awake only one waiter per resolution
     */
    private bool $awakeOnce = true;

    public function __construct(ReusableDeferred $deferred)
    {
        $this->deferred = $deferred;
        $this->waiters = new SplQueue();
    }

    public function __destruct()
    {
        // force awake all waiters
        $this->awakeOnce = false;
        $this->awakeWaiters();
    }

    public function wait(float $timeout = 0)
    {
        try {
            $result = $this->deferred->wait($timeout);
        } finally {
            $this->awakeWaiters();
        }

        return $result;
    }

    public function resolve($value): void
    {
        $this->deferred->resolve($value);
    }

    public function fail(Throwable $fail): void
    {
        $this->deferred->fail($fail);
    }

    public function isResolved(): bool
    {
        return $this->deferred->isResolved();
    }

    public function isWaiting(): bool
    {
        return $this->deferred->isWaiting();
    }

    /**
     * Block coroutine until previous wait result will be resolved
     *
     * @param float $timeout
     */
    public function waitForResolution(float $timeout = 0): void
    {
        $cid = Coroutine::getCid();

        $tid = null;
        $timedOut = false;

        if ($timeout > 0) {
            $tid = Timer::after(
                (int)($timeout * 1000),
                static function (int $cid) use (&$timedOut) {
                    $timedOut = true;

                    Coroutine::resume($cid);
                },
                $cid
            );
        }

        $this->waiters->enqueue($cid);

        Coroutine::yield();

        if ($timedOut) {
            $waiters = [];

            while (!$this->waiters->isEmpty()) {
                $waiter = $this->waiters->dequeue();

                if ($waiter !== $cid) {
                    $waiters[] = $waiter;
                }
            }

            foreach ($waiters as $waiter) {
                $this->waiters->enqueue($waiter);
            }

            throw new RuntimeException('waitForResolution timeout');
        }

        if ($tid !== null) {
            Timer::clear($tid);
        }
    }

    /**
     * If true - Awake only one waiter per resolution
     *
     * If false - Awake all waiters per resolution (this may cause an infinite looping in your code)
     *
     * This code will cause an infinite loop when $awakeOnce is false:
     *
     *  while ($deferred->isWaiting()) {
     *      $deferred->waitForResolution();
     *  }
     *
     * @param bool $awakeOnce
     */
    public function setAwaitOnce(bool $awakeOnce): void
    {
        $this->awakeOnce = $awakeOnce;
    }

    protected function awakeWaiters(): void
    {
        if (!$this->awakeOnce) {
            while (!$this->waiters->isEmpty()) {
                $cid = $this->waiters->dequeue();

                Coroutine::resume($cid);
            }

            return;
        }

        if (!$this->waiters->isEmpty()) {
            $cid = $this->waiters->dequeue();

            Coroutine::resume($cid);
        }
    }
}
