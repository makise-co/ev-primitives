<?php

declare(strict_types=1);

namespace MakiseCo\EvPrimitives;

use Throwable;

/**
 * @internal Used for representation of result of Deferred call
 */
class DeferredResult
{
    /**
     * Successful result
     *
     * @var mixed
     */
    private $success;

    /**
     * Error result
     */
    private ?Throwable $fail = null;

    private bool $resolved = false;

    public function resolve($value): void
    {
        if ($this->resolved) {
            throw new \LogicException('Already resolved');
        }

        $this->resolved = true;
        $this->success = $value;
    }

    public function fail(Throwable $reason): void
    {
        if ($this->resolved) {
            throw new \LogicException('Already resolved');
        }

        $this->resolved = true;
        $this->fail = $reason;
    }

    /**
     * @return mixed
     * @throws Throwable
     */
    public function unwrap()
    {
        if (!$this->resolved) {
            throw new \LogicException('Result is not resolved');
        }

        if ($this->fail !== null) {
            throw $this->fail;
        }

        return $this->success;
    }
}
