<?php

declare(strict_types=1);

namespace MakiseCo\EvPrimitives;

use Countable;
use MakiseCo\EvPrimitives\Exception\ChannelIsClosedException;
use MakiseCo\EvPrimitives\Exception\ChannelTimeoutException;
use Swoole\Coroutine\Channel as SwooleChannel;

use const SWOOLE_CHANNEL_CLOSED;
use const SWOOLE_CHANNEL_OK;
use const SWOOLE_CHANNEL_TIMEOUT;

/**
 * OOP Wrapper of Swoole Channel instead of returning false on channel error it is throwing exceptions
 */
class Channel implements Countable
{
    private SwooleChannel $channel;
    private bool $isClosed = false;

    public function __construct(int $size = 1)
    {
        $this->channel = new SwooleChannel($size);
    }

    public function __destruct()
    {
        $this->close();
    }

    public function close(): void
    {
        if (!$this->isClosed()) {
            $this->channel->close();

            $this->isClosed = true;
        }
    }

    /**
     * @param mixed $data
     * @param float $timeout -1 = no timeout
     *
     * @return bool success
     *
     * @throws Exception\ChannelIsClosedException
     * @throws Exception\ChannelTimeoutException
     */
    public function push($data, float $timeout = -1): bool
    {
        if (!$this->channel->push($data, $timeout)) {
            if ($this->channel->errCode === SWOOLE_CHANNEL_TIMEOUT) {
                throw new ChannelTimeoutException("Channel push is timed out");
            }

            if ($this->channel->errCode === SWOOLE_CHANNEL_CLOSED) {
                $this->isClosed = true;

                throw new ChannelIsClosedException();
            }

            return false;
        }

        return true;
    }

    /**
     * @param float $timeout -1 = no timeout
     *
     * @return mixed
     *
     * @throws Exception\ChannelIsClosedException
     * @throws Exception\ChannelTimeoutException
     */
    public function pop(float $timeout = -1)
    {
        $data = $this->channel->pop($timeout);

        if ($this->channel->errCode !== SWOOLE_CHANNEL_OK) {
            if ($this->channel->errCode === SWOOLE_CHANNEL_TIMEOUT) {
                throw new ChannelTimeoutException("Channel pop is timed out");
            }

            $this->isClosed = true;

            throw new ChannelIsClosedException();
        }

        return $data;
    }

    public function getSize(): int
    {
        return $this->channel->capacity;
    }

    public function count(): int
    {
        if ($this->isClosed()) {
            return 0;
        }

        return $this->channel->length();
    }

    /**
     * Alias of method count for compatibility with Swoole Channel API
     *
     * @return int
     */
    public function length(): int
    {
        return $this->count();
    }

    public function isClosed(): bool
    {
        return $this->isClosed;
    }

    public function isEmpty(): bool
    {
        if ($this->isClosed()) {
            return true;
        }

        return $this->channel->isEmpty();
    }

    public function isFull(): bool
    {
        if ($this->isClosed()) {
            return false;
        }

        return $this->channel->isFull();
    }
}
