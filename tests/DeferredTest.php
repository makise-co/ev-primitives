<?php

declare(strict_types=1);

namespace MakiseCo\EvPrimitives\Tests;

use Closure;
use InvalidArgumentException;
use MakiseCo\EvPrimitives\ConcurrencyLimitDeferred;
use MakiseCo\EvPrimitives\ConcurrencyLimitLockDeferred;
use MakiseCo\EvPrimitives\Deferred;
use MakiseCo\EvPrimitives\ReusableDeferred;
use Swoole\Coroutine;
use Throwable;

class DeferredTest extends CoroTestCase
{
    public function testSuccessResolution(): void
    {
        $ch = new Coroutine\Channel();
        $deferred = new ReusableDeferred();

        Coroutine::create(static function () use ($deferred, $ch) {
            try {
                $res = $deferred->wait();
                $ch->push($res);
            } catch (Throwable $e) {
                $ch->push($e);
            }
        });

        Coroutine::create(static function () use ($deferred) {
            $deferred->resolve(123);
        });

        $res = $ch->pop(0.1);

        self::assertSame(123, $res);
    }

    public function testErrorResolution(): void
    {
        $ch = new Coroutine\Channel();
        $deferred = new ReusableDeferred();

        Coroutine::create(static function () use ($deferred, $ch) {
            try {
                $res = $deferred->wait();
                $ch->push($res);
            } catch (Throwable $e) {
                $ch->push($e);
            }
        });

        Coroutine::create(static function () use ($deferred) {
            $deferred->fail(new InvalidArgumentException('wrong'));
        });

        /** @var InvalidArgumentException $res */
        $res = $ch->pop(0.1);

        self::assertInstanceOf(InvalidArgumentException::class, $res);
        self::assertSame('wrong', $res->getMessage());
    }

    public function testWait(): void
    {
        $deferred = new ReusableDeferred();

        $task = static function (int $result) use ($deferred) {
            $deferred->resolve($result);
        };

        $tasks = 3;

        for ($i = 1; $i <= $tasks; $i++) {
            Coroutine::create($task, $i * 100);
        }

        $results = [];
        for ($i = 0; $i < $tasks; $i++) {
            $results[] = $deferred->wait();
        }

        self::assertContains(100, $results);
        self::assertContains(200, $results);
        self::assertContains(300, $results);
    }

    public function testConcurrencyLimit(): void
    {
        $ch = new Coroutine\Channel(1);
        $deferred = new ConcurrencyLimitDeferred(new ReusableDeferred());

        $loop = static function (float $sleep) use ($deferred, $ch) {
            $cid = Coroutine::getCid();

            // prevent overlapping
            while ($deferred->isWaiting()) {
                $deferred->waitForResolution();
            }

            // imitate background processing
            Coroutine::create(static function () use ($sleep, $deferred, $cid) {
                Coroutine::sleep($sleep);

                $deferred->resolve($cid);
            });

            $ch->push($deferred->wait());
        };

        $cid1 = Coroutine::create($loop, 0.05);
        $cid2 = Coroutine::create($loop, 0.07);
        $cid3 = Coroutine::create($loop, 0.02);

        $res1 = $ch->pop();
        $res2 = $ch->pop();
        $res3 = $ch->pop();

        // coroutine results should be received sequentially
        self::assertSame($cid1, $res1);
        self::assertSame($cid2, $res2);
        self::assertSame($cid3, $res3);
    }

    public function testConcurrencyLimitLock(): void
    {
        $ch = new Coroutine\Channel(1);
        $deferred = new ConcurrencyLimitLockDeferred(new ReusableDeferred());

        $loop = static function (float $sleep) use ($deferred, $ch) {
            $cid = Coroutine::getCid();

            // prevent overlapping
            while ($deferred->isWaiting()) {
                $deferred->waitForResolution();
            }

            if ($cid % 2 === 0) {
                $deferred->lock();

                Coroutine::sleep($sleep);

                $ch->push($cid);
                $deferred->unlock();

                return;
            }

            // imitate background processing
            Coroutine::create(static function () use ($sleep, $deferred, $cid) {
                Coroutine::sleep($sleep);

                $deferred->resolve($cid);
            });

            $ch->push($deferred->wait());
        };

        $cid1 = Coroutine::create($loop, 0.05);
        $cid2 = Coroutine::create($loop, 0.07);
        $cid3 = Coroutine::create($loop, 0.02);

        $res1 = $ch->pop();
        $res2 = $ch->pop();
        $res3 = $ch->pop();

        // coroutine results should be received sequentially
        self::assertSame($cid1, $res1);
        self::assertSame($cid2, $res2);
        self::assertSame($cid3, $res3);
    }

    public function testAMPCase(): void
    {
        $ch = new Coroutine\Channel(3);

        $class = new class {
            private ?Deferred $busy = null;
            private ?Deferred $deferred = null;

            private Closure $poll;

            public function __construct()
            {
                $deferred = &$this->deferred;

                $this->poll = static function (int $cid, float $sleep) use (&$deferred) {
                    Coroutine::sleep($sleep);

                    if ($deferred !== null) {
                        $deferred->resolve($cid);
                    }
                };
            }

            public function send(Coroutine\Channel $ch, float $sleep): void
            {
                $cid = Coroutine::getCid();

                while ($this->busy) {
                    try {
                        $this->busy->wait();
                    } catch (Throwable $e) {
                        // Ignore failure from another operation.
                    }
                }

                try {
                    $this->busy = $this->deferred = new Deferred();

                    Coroutine::create($this->poll, $cid, $sleep);

                    $ch->push($this->deferred->wait());
                } finally {
                    $this->deferred = $this->busy = null;
                }
            }
        };

        $cid1 = Coroutine::create(Closure::fromCallable([$class, 'send']), $ch, 0.1);
        $cid2 = Coroutine::create(Closure::fromCallable([$class, 'send']), $ch, 0.1);
        $cid3 = Coroutine::create(Closure::fromCallable([$class, 'send']), $ch, 0.1);

        $res1 = $ch->pop();
        $res2 = $ch->pop();
        $res3 = $ch->pop();

        // coroutine results should be received sequentially
        self::assertSame($cid1, $res1);
        self::assertSame($cid2, $res2);
        self::assertSame($cid3, $res3);
    }
}
