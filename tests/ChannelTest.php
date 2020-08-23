<?php

declare(strict_types=1);

namespace MakiseCo\EvPrimitives\Tests;

use MakiseCo\EvPrimitives\Channel;
use MakiseCo\EvPrimitives\Exception\ChannelIsClosedException;
use MakiseCo\EvPrimitives\Exception\ChannelTimeoutException;
use Swoole\Coroutine;

class ChannelTest extends CoroTestCase
{
    public function testPush(): void
    {
        $ch = new Channel(2);

        $ch->push(1);
        self::assertSame(1, $ch->count());

        $ch->push(2);
        self::assertSame(2, $ch->count());
    }

    public function testPushTimeout(): void
    {
        $this->expectException(ChannelTimeoutException::class);

        $ch = new Channel(1);

        $ch->push(1);
        $ch->push(2, 0.001);
    }

    public function testPushNoTimeout(): void
    {
        $done = false;
        $ch = new Channel(1);

        Coroutine::create(static function () use (&$done, $ch) {
            $ch->push(1);
            $ch->push(2);

            $done = true;
        });

        $ticks = 0;

        while (!$done) {
            Coroutine::sleep(0.001);

            if (++$ticks === 2) {
                self::assertSame(1, $ch->pop());
                self::assertSame(2, $ch->pop());
            }
        }
    }

    public function testPushChannelIsClosed(): void
    {
        $this->expectException(ChannelIsClosedException::class);

        $ch = new Channel(1);
        $ch->close();

        self::assertTrue($ch->isClosed());

        $ch->push(1);
    }

    public function testPop(): void
    {
        $ch = new Channel(2);

        $ch->push(1);
        $ch->push(2);

        self::assertSame(1, $ch->pop());
        self::assertSame(1, $ch->count());

        self::assertSame(2, $ch->pop());
        self::assertSame(0, $ch->count());
    }

    public function testPopTimeout(): void
    {
        $this->expectException(ChannelTimeoutException::class);

        $ch = new Channel(1);

        $ch->push(1);
        $ch->push(2, 0.001);
    }

    public function testPopChannelIsClosed(): void
    {
        $this->expectException(ChannelIsClosedException::class);

        $ch = new Channel(1);

        Coroutine::create(static function () use ($ch) {
            $ch->push(1);
            $ch->push(2);
            $ch->close();
        });

        $ch->pop();
        $ch->pop();
        $ch->pop();
    }
}
