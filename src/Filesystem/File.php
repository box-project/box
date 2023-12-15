<?php

namespace KevinGH\Box\Filesystem;

interface File
{
    public function getPath(): string;
    public function getContents(): string;
}