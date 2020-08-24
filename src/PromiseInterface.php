<?php

declare(strict_types=1);

namespace MakiseCo\EvPrimitives;

use Throwable;

interface PromiseInterface
{
    /**
     * Wait for promise result
     *
     * @param float $timeout
     *
     * @return mixed on successful resolution
     *
     * @throws Throwable on failed resolution
     */
    public function wait(float $timeout = 0);

    /**
     * Successful resolve promise
     *
     * @param mixed $value
     */
    public function resolve($value): void;

    /**
     * Reject promise (error resolution)
     *
     * @param Throwable $fail
     */
    public function fail(Throwable $fail): void;

    /**
     * Is promise result ready for consuming?
     *
     * @return bool
     */
    public function isResolved(): bool;

    /**
     * Is anybody waiting for promise resolution?
     *
     * @return bool
     */
    public function isWaiting(): bool;
}
