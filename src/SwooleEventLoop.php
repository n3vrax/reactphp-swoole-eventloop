<?php
/**
 * Created by PhpStorm.
 * User: n3vra
 * Date: 7/8/2018
 * Time: 1:59 AM
 */

declare(strict_types=1);

namespace N3vrax\React\EventLoop;

use React\EventLoop\LoopInterface;
use React\EventLoop\SignalsHandler;
use React\EventLoop\Tick\FutureTickQueue;
use React\EventLoop\TimerInterface;
use Swoole\Event;

/**
 * Class SwooleEventLoop
 * @package N3vrax\React\EventLoop
 */
final class SwooleEventLoop implements LoopInterface
{
    /** @var array  */
    private $readStreams = [];

    /** @var array  */
    private $readListeners = [];

    /** @var array  */
    private $writeStreams = [];

    /** @var array  */
    private $writeListeners = [];

    /** @var array  */
    private $timers = [];

    /** @var  bool */
    private $running;

    /** @var  bool */
    private $pcntl;

    /** @var  SignalsHandler */
    private $signals;

    /** @var  FutureTickQueue */
    private $futureTickQueue;

    /**
     * SwooleEventLoop constructor.
     */
    public function __construct()
    {
        if (!extension_loaded('swoole')) {
            throw new \RuntimeException('Swoole extension is not loaded');
        }

        $this->pcntl = \extension_loaded('pcntl');
        $this->signals = new SignalsHandler();
        $this->futureTickQueue = new FutureTickQueue();
    }

    /**
     * @param resource $stream
     * @param callable $listener
     */
    public function addReadStream($stream, $listener)
    {
        $fd = (int) $stream;
        if (isset($this->readStreams[$fd])) {
            Event::set($fd, $listener, null, SWOOLE_EVENT_READ);
            $this->readListeners[$fd] = $listener;
            return;
        }

        Event::add($fd, $listener, null, SWOOLE_EVENT_READ);

        $this->readStreams[$fd] = $stream;
        $this->readListeners[$fd] = $listener;
    }

    /**
     * @param resource $stream
     * @param callable $listener
     */
    public function addWriteStream($stream, $listener)
    {
        $fd = (int) $stream;
        if (isset($this->writeStreams[$fd])) {
            Event::set($fd, null, $listener, SWOOLE_EVENT_WRITE);
            $this->writeListeners[$fd] = $listener;
            return;
        }

        Event::add($fd, null, $listener, SWOOLE_EVENT_WRITE);

        $this->writeStreams[$fd] = $stream;
        $this->writeListeners[$fd] = $listener;
    }

    /**
     * @param resource $stream
     */
    public function removeReadStream($stream)
    {
        $fd = (int) $stream;

        unset(
            $this->readStreams[$fd],
            $this->readListeners[$fd]
        );

        Event::del($fd);
    }

    /**
     * @param resource $stream
     */
    public function removeWriteStream($stream)
    {
        $fd = (int) $stream;

        unset(
            $this->writeStreams[$fd],
            $this->writeListeners[$fd]
        );

        Event::del($fd);
    }

    /**
     * @param float|int $interval
     * @param callable  $callback
     *
     * @return TimerInterface
     */
    public function addTimer($interval, $callback): TimerInterface
    {
        return $this->createTimer($interval, $callback, false);
    }

    /**
     * @param float|int $interval
     * @param callable  $callback
     *
     * @return TimerInterface
     */
    public function addPeriodicTimer($interval, $callback): TimerInterface
    {
        return $this->createTimer($interval, $callback, true);
    }

    /**
     * @param          $interval
     * @param callable $callback
     * @param bool     $periodic
     *
     * @return TimerInterface
     */
    protected function createTimer($interval, callable $callback, bool $periodic = false): TimerInterface
    {
        if ($periodic) {
            // wraps the original callback to convert the timer id into the registered timer instance
            $callback = function ($timerId) use ($callback) {
                $timer = $this->timers[$timerId];
                $callback($timer);
            };
        }

        $interval = (int) $interval * 1000;
        $interval = $interval > 0 ? $interval : 1; // no 0 timers allowed, make sure we put at least 1ms

        $timerId = $periodic
            ? \Swoole\Timer::tick($interval, $callback)
            : \Swoole\Timer::after($interval, $callback);

        $timer = new Timer($timerId, $interval, $callback, $periodic);
        $this->timers[$timerId] = $timer;

        return $timer;
    }

    /**
     * @param TimerInterface $timer
     */
    public function cancelTimer(TimerInterface $timer)
    {
        $timerId = null;
        foreach ($this->timers as $tId => $t) {
            if ($t === $timer) {
                $timerId = $tId;
                break;
            }
        }

        if ($timerId) {
            unset($this->timers[$timerId]);
            \Swoole\Timer::clear($timerId);
        }
    }

    /**
     * @param callable $listener
     */
    public function futureTick($listener)
    {
        $this->futureTickQueue->add($listener);
    }

    /**
     * @param int      $signal
     * @param callable $listener
     */
    public function addSignal($signal, $listener)
    {
        if ($this->pcntl === false) {
            throw new \BadMethodCallException('Pcntl extension not loaded');
        }

        $first = $this->signals->count($signal) === 0;
        $this->signals->add($signal, $listener);

        if ($first) {
            \pcntl_signal($signal, [$this->signals, 'call']);
        }
    }

    /**
     * @param int      $signal
     * @param callable $listener
     */
    public function removeSignal($signal, $listener)
    {
        if (!$this->signals->count($signal)) {
            return;
        }

        $this->signals->remove($signal, $listener);

        if ($this->signals->count($signal) === 0) {
            \pcntl_signal($signal, SIG_DFL);
        }
    }

    /**
     * In this case, nothing to start
     */
    public function run()
    {
        $this->running = true;

        Event::cycle(function () {
            if (!$this->running) {
                Event::cycle(null);
                \swoole_event_exit();
            }

            $this->futureTickQueue->tick();
        });
    }

    /**
     * Stop the event loop
     */
    public function stop()
    {
        $this->running = false;
    }
}
