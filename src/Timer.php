<?php

declare(strict_types=1);

namespace MakiseCo\EvPrimitives;

use Closure;
use LogicException;
use Swoole\Timer as SwooleTimer;

class Timer
{
    /**
     * Timer ID
     */
    private int $id = 0;

    /**
     * Timer interval in milliseconds
     */
    private int $interval;

    /**
     * Is overlapping allowed?
     */
    private bool $overlapping;

    /**
     * Closure to be invoked on every tick
     */
    private Closure $callback;

    /**
     * Is timer started?
     */
    private bool $isStarted = false;

    /**
     * Is timer in running (on tick) state?
     */
    private bool $isRunning = false;

    /**
     * If true timer will not be stopped when object is destructed
     */
    private bool $isDetached = false;

    /**
     * @param int $interval Timer interval in milliseconds
     * @param Closure $callback Closure to be invoked on every tick
     * @param bool $overlapping Is overlapping allowed?
     */
    public function __construct(int $interval, Closure $callback, bool $overlapping = true)
    {
        $this->setInterval($interval);
        $this->setOverlapping($overlapping);

        $this->callback = $callback;
    }

    public function __destruct()
    {
        if ($this->isDetached) {
            return;
        }

        $this->stop();
    }

    /**
     * Start timer
     *
     * @throws LogicException when timer is already started
     */
    public function start(): void
    {
        if ($this->isStarted) {
            throw new LogicException('Timer already started');
        }

        $this->isStarted = true;

        $this->id = SwooleTimer::tick(
            $this->interval,
            Closure::fromCallable([$this, 'tickHandler'])
        );
    }

    /**
     * Stop timer
     */
    public function stop(): void
    {
        if ($this->isStarted) {
            $this->isStarted = false;

            SwooleTimer::clear($this->id);
        }
    }

    /**
     * Is timer started?
     */
    public function isStarted(): bool
    {
        return $this->isStarted;
    }

    /**
     * Is timer in running (on tick) state?
     */
    public function isRunning(): bool
    {
        return $this->isRunning;
    }

    /**
     * Is timer detached from object?
     *
     * @return bool
     */
    public function isDetached(): bool
    {
        return $this->isDetached;
    }

    /**
     * Get timer ticks counter
     */
    public function getTicks(): int
    {
        if ($this->isStarted) {
            return SwooleTimer::info($this->id)['round'] ?? 0;
        }

        return 0;
    }

    /**
     * Prevent timer from being stopped when object is destructed
     *
     * @return int Timer ID for manual management
     */
    public function detach(): int
    {
        $this->isDetached = true;

        return $this->id;
    }

    /**
     * Allow timer to be stopped when object is destructed
     */
    public function attach(): void
    {
        $this->isDetached = false;
    }

    /**
     * Change timer interval
     *
     * @param int $interval milliseconds
     */
    public function setInterval(int $interval): void
    {
        if ($interval < 1) {
            throw new \InvalidArgumentException('Timer interval must be at least 1 ms');
        }

        $this->interval = $interval;

        if ($this->isStarted()) {
            $this->stop();
            $this->start();
        }
    }

    /**
     * Get timer interval
     *
     * @return int milliseconds
     */
    public function getInterval(): int
    {
        return $this->interval;
    }

    /**
     * Is overlapping allowed?
     */
    public function isOverlapping(): bool
    {
        return $this->overlapping;
    }

    /**
     * Set timer overlapping
     *
     * @param bool $overlapping if true - overlapping is allowed
     */
    public function setOverlapping(bool $overlapping): void
    {
        $this->overlapping = $overlapping;
    }

    protected function tickHandler(): void
    {
        // prevent overlapping
        if (!$this->overlapping && $this->isRunning) {
            return;
        }

        $this->isRunning = true;

        ($this->callback)($this);

        $this->isRunning = false;
    }
}
