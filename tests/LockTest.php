<?php

declare(strict_types=1);

namespace MakiseCo\EvPrimitives\Tests;

use MakiseCo\EvPrimitives\Lock;
use Swoole\Coroutine;

class LockTest extends CoroTestCase
{
    public function testLock(): void
    {
        $class = new class {
            private Lock $lock;
            private int $counter = 0;

            public function __construct()
            {
                $this->lock = new Lock();
            }

            public function getConnection(): int
            {
                $this->lock->lock();
                Coroutine::sleep(0.001);
                $this->lock->unlock();

                return ++$this->counter;
            }
        };

        $ch = new Coroutine\Channel();

        $func = static function () use ($ch, $class) {
            $ch->push($class->getConnection());
        };

        $counter = 4;

        for ($i = 0; $i < $counter; $i++) {
            Coroutine::create($func);
        }

        $results = [];
        for ($i = 0; $i < $counter; $i++) {
            $results[] = $ch->pop(0.5);
        }

        self::assertSame([1, 2, 3, 4], $results);
    }

    public function testWait(): void
    {
        $class = new class {
            private Lock $lock;
            private int $counter = 0;

            public function __construct()
            {
                $this->lock = new Lock();
            }

            public function getConnection(): int
            {
                while ($this->lock->isLocked()) {
                    $this->lock->wait();
                }

                $this->lock->lock();
                Coroutine::sleep(0.001);
                $this->lock->unlock();

                return ++$this->counter;
            }
        };

        $ch = new Coroutine\Channel();

        $func = static function () use ($ch, $class) {
            $ch->push($class->getConnection());
        };

        $counter = 4;

        for ($i = 0; $i < $counter; $i++) {
            Coroutine::create($func);
        }

        $results = [];
        for ($i = 0; $i < $counter; $i++) {
            $results[] = $ch->pop(0.5);
        }

        self::assertSame([1, 2, 3, 4], $results);
    }
}
