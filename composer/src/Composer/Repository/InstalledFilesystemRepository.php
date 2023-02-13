<?php declare(strict_types=1);


namespace Composer\Repository;


class InstalledFilesystemRepository extends FilesystemRepository implements InstalledRepositoryInterface
{
    public function getRepoName()
    {
        return 'installed ' . parent::getRepoName();
    }


    public function isFresh()
    {
        return !$this->file->exists();
    }
}
