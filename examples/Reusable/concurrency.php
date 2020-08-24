<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use MakiseCo\EvPrimitives\ReusableDeferred;
use Swoole\Coroutine;

use function Swoole\Coroutine\run;

// force autoload to resolve classes before measurements
class_exists(ReusableDeferred::class);
class_exists(\MakiseCo\EvPrimitives\DeferredResult::class);

class Worker
{
    private ReusableDeferred $deferred;

    public function __construct()
    {
        $this->deferred = new ReusableDeferred();
    }

    public function calculate($result)
    {
        $backgroundTask = static function (ReusableDeferred $deferred, $result) {
            Coroutine::sleep(0.05);

            if ($result instanceof \Throwable) {
                $deferred->fail($result);
            } else {
                $deferred->resolve($result);
            }
        };

        Coroutine::create($backgroundTask, $this->deferred, $result);

        return $this->deferred->wait();
    }
}

run(static function () {
    $worker = new Worker();
    $ch = new Coroutine\Channel();

    $collector = static function (Coroutine\Channel $ch, Worker $worker, $result)  {
        try {
            $ch->push($worker->calculate($result));
        } catch (\Throwable $e) {
            $ch->push(null);
        }
    };

    $tasksCount = 100;

    // create parallel tasks
    for ($i = 0; $i < $tasksCount; $i++) {
        Coroutine::create($collector, $ch, $worker, $i);
    }

    $start = microtime(true);

    $results = [];

    for ($i = 0; $i < $tasksCount; $i++) {
        $results[] = $ch->pop(0.1);
    }

    $end = microtime(true);

    $approxTime = 0.05 * $tasksCount;

    print_r($results);
    printf("Time for calculations: %f secs (linear approximated: %f)\n\n", $end - $start, $approxTime);
});
