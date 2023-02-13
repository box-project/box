<?php

namespace React\Promise;

class Deferred implements PromisorInterface
{
private $promise;
private $resolveCallback;
private $rejectCallback;
private $notifyCallback;
private $canceller;

public function __construct(callable $canceller = null)
{
$this->canceller = $canceller;
}

public function promise()
{
if (null === $this->promise) {
$this->promise = new Promise(function ($resolve, $reject, $notify) {
$this->resolveCallback = $resolve;
$this->rejectCallback = $reject;
$this->notifyCallback = $notify;
}, $this->canceller);
$this->canceller = null;
}

return $this->promise;
}

public function resolve($value = null)
{
$this->promise();

\call_user_func($this->resolveCallback, $value);
}

public function reject($reason = null)
{
$this->promise();

\call_user_func($this->rejectCallback, $reason);
}





public function notify($update = null)
{
$this->promise();

\call_user_func($this->notifyCallback, $update);
}





public function progress($update = null)
{
$this->notify($update);
}
}
