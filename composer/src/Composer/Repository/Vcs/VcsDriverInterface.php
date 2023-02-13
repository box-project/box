<?php declare(strict_types=1);











namespace Composer\Repository\Vcs;

use Composer\Config;
use Composer\IO\IOInterface;





interface VcsDriverInterface
{



public function initialize(): void;







public function getComposerInformation(string $identifier): ?array;




public function getFileContent(string $file, string $identifier): ?string;




public function getChangeDate(string $identifier): ?\DateTimeImmutable;






public function getRootIdentifier(): string;






public function getBranches(): array;






public function getTags(): array;






public function getDist(string $identifier): ?array;






public function getSource(string $identifier): array;




public function getUrl(): string;








public function hasComposerFile(string $identifier): bool;




public function cleanup(): void;









public static function supports(IOInterface $io, Config $config, string $url, bool $deep = false): bool;
}
