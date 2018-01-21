<?php

declare(strict_types=1);

namespace KevinGH\Box;

final class RetrieveRelativeBasePath
{
    private $basePath;
    private $basePathRegex;

    public function __construct(string $basePath)
    {
        $this->basePath = $basePath;
        $this->basePathRegex = '/'.preg_quote($basePath.DIRECTORY_SEPARATOR, '/').'/';
    }

    public function __invoke(string $path)
    {
        return preg_replace(
            $this->basePathRegex,
            '',
            $path
        );
    }

    public function getBasePath(): string
    {
        return $this->basePath;
    }
}