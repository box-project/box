<?php declare(strict_types=1);











namespace Composer\Package;

use Composer\Repository\RepositoryInterface;











interface PackageInterface
{
public const DISPLAY_SOURCE_REF_IF_DEV = 0;
public const DISPLAY_SOURCE_REF = 1;
public const DISPLAY_DIST_REF = 2;






public function getName(): string;






public function getPrettyName(): string;











public function getNames(bool $provides = true): array;




public function setId(int $id): void;






public function getId(): int;




public function isDev(): bool;






public function getType(): string;






public function getTargetDir(): ?string;






public function getExtra(): array;







public function setInstallationSource(?string $type): void;







public function getInstallationSource(): ?string;






public function getSourceType(): ?string;






public function getSourceUrl(): ?string;






public function getSourceUrls(): array;






public function getSourceReference(): ?string;






public function getSourceMirrors(): ?array;




public function setSourceMirrors(?array $mirrors): void;






public function getDistType(): ?string;






public function getDistUrl(): ?string;






public function getDistUrls(): array;






public function getDistReference(): ?string;






public function getDistSha1Checksum(): ?string;






public function getDistMirrors(): ?array;




public function setDistMirrors(?array $mirrors): void;






public function getVersion(): string;






public function getPrettyVersion(): string;












public function getFullPrettyVersion(bool $truncate = true, int $displayMode = self::DISPLAY_SOURCE_REF_IF_DEV): string;






public function getReleaseDate(): ?\DateTimeInterface;






public function getStability(): string;







public function getRequires(): array;







public function getConflicts(): array;







public function getProvides(): array;







public function getReplaces(): array;







public function getDevRequires(): array;








public function getSuggests(): array;












public function getAutoload(): array;












public function getDevAutoload(): array;







public function getIncludePaths(): array;




public function setRepository(RepositoryInterface $repository): void;






public function getRepository(): ?RepositoryInterface;






public function getBinaries(): array;




public function getUniqueName(): string;






public function getNotificationUrl(): ?string;




public function __toString(): string;




public function getPrettyString(): string;

public function isDefaultBranch(): bool;






public function getTransportOptions(): array;






public function setTransportOptions(array $options): void;

public function setSourceReference(?string $reference): void;

public function setDistUrl(?string $url): void;

public function setDistType(?string $type): void;

public function setDistReference(?string $reference): void;




public function setSourceDistReferences(string $reference): void;
}
