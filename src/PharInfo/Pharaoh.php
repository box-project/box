<?php

declare(strict_types=1);

/*
 * This file is part of the box project.
 *
 * (c) Kevin Herrera <kevin@herrera.io>
 *     Théo Fidry <theo.fidry@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace KevinGH\Box\PharInfo;

use function basename;
use function KevinGH\Box\FileSystem\remove;
use ParagonIE\Pharaoh\Pharaoh as ParagoniePharaoh;

final class Pharaoh extends ParagoniePharaoh
{
    private $fileName;

    /** @var null|PharInfo */
    private $pharInfo;

    /** @var null|string */
    private $path;

    /**
     * {@inheritdoc}
     */
    public function __construct(string $file, ?string $alias = null)
    {
        parent::__construct($file, $alias);

        $this->fileName = basename($file);
    }

    /**
     * {@inheritdoc}
     */
    public function __destruct()
    {
        unset($this->pharInfo);

        parent::__destruct();

        remove($this->tmp);
    }

    public function getFileName(): string
    {
        return $this->fileName;
    }

    public function getPharInfo(): PharInfo
    {
        if (null === $this->pharInfo || $this->path !== $this->phar->getPath()) {
            $this->path = $this->phar->getPath();
            $this->pharInfo = new PharInfo($this->path);
        }

        return $this->pharInfo;
    }
}
