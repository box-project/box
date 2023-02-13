<?php










namespace Symfony\Contracts\Service\Attribute;

use Symfony\Contracts\Service\ServiceSubscriberTrait;







#[\Attribute(\Attribute::TARGET_METHOD)]
final class SubscribedService
{




public function __construct(
public ?string $key = null
) {
}
}
