<?php

declare(strict_types=1);

namespace KevinGH\Box\RequirementChecker;

enum RequirementType: string
{
    case PHP = 'php';
    case EXTENSION = 'extension';
    case EXTENSION_CONFLICT = 'extension-conflict';
}