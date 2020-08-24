<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use MakiseCo\EvPrimitives\ReusableDeferred;
use Swoole\Coroutine;

use function Swoole\Coroutine\run;

// force autoload to resolve classes before measurements
class_exists(ReusableDeferred::class);
class_exists(\MakiseCo\EvPrimitives\DeferredResult::class);

run(static function () {
    $deferred = new ReusableDeferred();

    $task = static function (int $result) use ($deferred): void {
        Coroutine::sleep(0.05);

        $deferred->resolve($result);
    };

    Coroutine::create($task, 123);
    Coroutine::create($task, 456);
    Coroutine::create($task, 789);

    var_dump("Result 1 is: {$deferred->wait()}");
    var_dump("Result 2 is: {$deferred->wait()}");
    var_dump("Result 3 is: {$deferred->wait()}");
});
