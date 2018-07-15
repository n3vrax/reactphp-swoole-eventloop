<?php
/**
 * Created by PhpStorm.
 * User: n3vra
 * Date: 7/8/2018
 * Time: 1:59 AM
 */

declare(strict_types=1);

namespace n3vrax\React\EventLoop;

use React\EventLoop\TimerInterface;

/**
 * Class Timer
 * @package n3vrax\React\EventLoop
 */
final class Timer implements TimerInterface
{
    /** @var  integer */
    private $timerId;

    /** @var  bool */
    private $periodic;

    /** @var  float */
    private $interval;

    /** @var  callable */
    private $callback;

    /**
     * SwooleTimer constructor.
     *
     * @param int           $timerId
     * @param float         $interval
     * @param callable      $callback
     * @param bool          $periodic
     */
    public function __construct(
        int $timerId,
        float $interval,
        callable $callback,
        bool $periodic = false
    ) {
        $this->timerId = $timerId;
        $this->interval = $interval;
        $this->periodic = $periodic;
        $this->callback = $callback;
    }

    /**
     * @return int
     */
    public function getTimerId(): int
    {
        return $this->timerId;
    }

    /**
     * @return callable
     */
    public function getCallback(): callable
    {
        return $this->callback;
    }

    /**
     * @return float
     */
    public function getInterval(): float
    {
        return $this->interval;
    }

    /**
     * @return bool
     */
    public function isPeriodic(): bool
    {
        return $this->periodic;
    }
}
