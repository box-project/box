<?php declare(strict_types=1);











namespace Composer\Package;








interface CompletePackageInterface extends PackageInterface
{





public function getScripts(): array;




public function setScripts(array $scripts): void;






public function getRepositories(): array;






public function setRepositories(array $repositories): void;






public function getLicense(): array;






public function setLicense(array $license): void;






public function getKeywords(): array;






public function setKeywords(array $keywords): void;






public function getDescription(): ?string;




public function setDescription(string $description): void;






public function getHomepage(): ?string;




public function setHomepage(string $homepage): void;








public function getAuthors(): array;






public function setAuthors(array $authors): void;






public function getSupport(): array;






public function setSupport(array $support): void;








public function getFunding(): array;






public function setFunding(array $funding): void;




public function isAbandoned(): bool;




public function getReplacementPackage(): ?string;




public function setAbandoned($abandoned): void;






public function getArchiveName(): ?string;




public function setArchiveName(string $name): void;






public function getArchiveExcludes(): array;






public function setArchiveExcludes(array $excludes): void;
}
