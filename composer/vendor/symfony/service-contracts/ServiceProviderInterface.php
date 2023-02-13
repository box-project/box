<?php










namespace Symfony\Contracts\Service;

use Psr\Container\ContainerInterface;







interface ServiceProviderInterface extends ContainerInterface
{











public function getProvidedServices(): array;
}
