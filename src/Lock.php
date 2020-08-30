<?php

declare(strict_types=1);

namespace MakiseCo\EvPrimitives;

use Swoole\Coroutine;
use Swoole\Timer;

class Lock
{
    private array $queue = [];
    private bool $locked = false;

    public function isLocked(): bool
    {
        return $this->locked;
    }

    public function lock(float $timeout = 0): void
    {
        if (!$this->locked) {
            $this->locked = true;

            return;
        }

        if ($this->blockWithTimeout($timeout)) {
            throw new Exception\LockTimeoutException('Lock timeout');
        }

        $this->locked = true;
    }

    public function wait(float $timeout = 0): void
    {
        if (!$this->locked) {
            return;
        }

        if ($this->blockWithTimeout($timeout)) {
            throw new Exception\LockTimeoutException('Wait timeout');
        }
    }

    public function unlock(): void
    {
        $this->locked = false;

        $queue = $this->queue;
        $this->queue = [];

        foreach ($queue as $key => $cid) {
            Coroutine::resume($cid);
        }
    }

    private function blockWithTimeout(float $timeout): bool
    {
        if ($timeout <= 0.0) {
            $this->block();

            return false;
        }

        $timedOut = false;
        $cid = Coroutine::getCid();
        $tid = Timer::after((int)($timeout * 1000), static function () use ($cid, &$timedOut) {
            $timedOut = true;
            Coroutine::resume($cid);
        });

        $this->block();

        if (!$timedOut) {
            Timer::clear($tid);
        }

        return $timedOut;
    }

    private function block(): void
    {
        $this->queue[] = Coroutine::getCid();
        Coroutine::yield();
    }
}
