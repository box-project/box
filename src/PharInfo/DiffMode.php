<?php

declare(strict_types=1);

namespace KevinGH\Box\PharInfo;

enum DiffMode: string
{
    case LIST = 'list-diff';
    case GIT = 'git-diff';
    case GNU = 'gnu-diff';
}
