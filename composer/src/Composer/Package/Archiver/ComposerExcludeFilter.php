<?php declare(strict_types=1);











namespace Composer\Package\Archiver;






class ComposerExcludeFilter extends BaseExcludeFilter
{




public function __construct(string $sourcePath, array $excludeRules)
{
parent::__construct($sourcePath);
$this->excludePatterns = $this->generatePatterns($excludeRules);
}
}
