<?php










namespace Symfony\Component\String\Inflector;

interface InflectorInterface
{







public function singularize(string $plural): array;








public function pluralize(string $singular): array;
}
