<?php declare(strict_types=1);











namespace Composer\Package\Archiver;






interface ArchiverInterface
{











public function archive(string $sources, string $target, string $format, array $excludes = [], bool $ignoreFilters = false): string;









public function supports(string $format, ?string $sourceType): bool;
}
