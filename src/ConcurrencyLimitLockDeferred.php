<?php

declare(strict_types=1);

namespace MakiseCo\EvPrimitives;

/**
 * Concurrency Limit Lock Deferred implementation
 *
 * Giving an ability to mark deferred as waiting without performing an actual deferred operation
 *
 * @see examples/ConcurrencyLimitLock/concurrency.php
 */
final class ConcurrencyLimitLockDeferred extends ConcurrencyLimitDeferred
{
    private bool $isLocked = false;

    /**
     * Mark deferred as waiting
     */
    public function lock(): void
    {
        $this->isLocked = true;
    }

    /**
     * Unlock deferred and awake waiters
     */
    public function unlock(): void
    {
        $this->isLocked = false;

        if (!$this->isWaiting()) {
            $this->awakeWaiters();
        }
    }

    public function isWaiting(): bool
    {
        return $this->isLocked || parent::isWaiting();
    }
}
