<?php declare(strict_types=1);











namespace Composer\Package;











interface RootPackageInterface extends CompletePackageInterface
{





public function getAliases(): array;




public function getMinimumStability(): string;








public function getStabilityFlags(): array;








public function getReferences(): array;




public function getPreferStable(): bool;






public function getConfig(): array;






public function setRequires(array $requires): void;






public function setDevRequires(array $devRequires): void;






public function setConflicts(array $conflicts): void;






public function setProvides(array $provides): void;






public function setReplaces(array $replaces): void;







public function setAutoload(array $autoload): void;







public function setDevAutoload(array $devAutoload): void;






public function setStabilityFlags(array $stabilityFlags): void;




public function setMinimumStability(string $minimumStability): void;




public function setPreferStable(bool $preferStable): void;






public function setConfig(array $config): void;






public function setReferences(array $references): void;






public function setAliases(array $aliases): void;






public function setSuggests(array $suggests): void;




public function setExtra(array $extra): void;
}
