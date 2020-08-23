<?php

declare(strict_types=1);

namespace MakiseCo\EvPrimitives\Tests;

use MakiseCo\EvPrimitives\Timer;
use Swoole\Coroutine;

class TimerTest extends CoroTestCase
{
    public function testTick(): void
    {
        $actualTicks = 0;
        $isRunning = null;

        $timer = new Timer(
            10,
            static function (Timer $timer) use (&$actualTicks, &$isRunning) {
                if (++$actualTicks === 2) {
                    $timer->stop();
                }

                $isRunning = $timer->isRunning();
            }
        );

        self::assertFalse($timer->isStarted());
        self::assertFalse($timer->isRunning());

        $timer->start();

        self::assertTrue($timer->isStarted());
        self::assertFalse($timer->isRunning());

        Coroutine::sleep(0.1);

        self::assertSame(2, $actualTicks);
        self::assertSame(0, $timer->getTicks());

        self::assertFalse($timer->isStarted());
        self::assertTrue($isRunning);
    }

    public function testOverlapping(): void
    {
        $actualTicks = 0;

        $timer = new Timer(
            10,
            static function () use (&$actualTicks) {
                ++$actualTicks;
                Coroutine::sleep(0.1);
            },
            true
        );
        $timer->start();

        Coroutine::sleep(0.1);

        $timer->stop();

        self::assertSame(10, $actualTicks);
    }

    public function testNoOverlapping(): void
    {
        $actualTicks = 0;

        $timer = new Timer(
            10,
            static function () use (&$actualTicks) {
                ++$actualTicks;
                Coroutine::sleep(0.1);
            },
            false
        );
        $timer->start();

        Coroutine::sleep(0.1);

        $timer->stop();

        self::assertSame(1, $actualTicks);
    }
}
