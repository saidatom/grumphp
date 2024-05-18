<?php

declare(strict_types=1);

namespace GrumPHP\Locator;

use Gitonomy\Git\Diff\Diff;
use Gitonomy\Git\Diff\File;
use GrumPHP\Collection\FilesCollection;
use GrumPHP\Git\GitRepository;
use GrumPHP\Util\Filesystem;
use GrumPHP\Util\Paths;
use SplFileInfo;

/**
 * Class Git.
 */
class ChangedFiles
{
    /**
     * @var GitRepository
     */
    private $repository;

    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @var Paths
     */
    private $paths;

    public function __construct(GitRepository $repository, Filesystem $filesystem, Paths $paths)
    {
        $this->repository = $repository;
        $this->filesystem = $filesystem;
        $this->paths = $paths;
    }

    public function locateFromGitRepository(): FilesCollection
    {
        $diff = $this->repository->getWorkingCopy()->getDiffStaged();

        return $this->parseFilesFromDiff($diff);
    }

    public function getLocalRef(): ?string
    {
        return $this->repository->run('symbolic-ref', ['HEAD']);
    }
    public function getLocalSHA1(): ?string
    {
        return $this->repository->run('rev-parse', ['HEAD']);
    }
    private function getRemoteRef(): ?string
    {
        return $this->repository->tryToRunWithFallback(
            function (): ?string {
                return $this->repository->run('rev-parse', ['--abbrev-ref', '--symbolic-full-name', '@{u}']);
            },
            'null'
        );
    }
    
    public function getRemoteSHA1(): ?string
    {
        if ($this->getRemoteRef() === 'null') {
            return null;
        }
        /**
         * @psalm-suppress PossiblyUndefinedArrayOffset
         * @psalm-suppress PossiblyNullArgument
         */
        list($remoteName, $branchName) = explode('/', $this->getRemoteRef(), 2);
        $output = $this->repository->run('ls-remote', [$remoteName, $branchName]);
        return $output ? strtok($output, "\t") : null;
    }

    /**
     * @return FilesCollection
     */
    public function locateFromGitDiff(string $fromSha, ?string $toSha): FilesCollection
    {
        if (empty($toSha)) {
            $toSha = 'HEAD';
        }
        $files = [];
        $commits = $fromSha . '..' . $toSha;
        $diff = $this->repository->tryToRunWithFallback(
            function () use ($commits): ?string {
                return $this->repository->run('diff', ['--name-only', trim($commits), '--oneline']);
            },
            'null'
        );
        if ($diff === 'null') {
            return new FilesCollection($files);
        }

        foreach (explode("\n", $diff) as $file) {
            $fileObject = new SplFileInfo($file);
            $files[] = $fileObject;
        }

        return new FilesCollection($files);
    }

    public function locateFromRawDiffInput(string $rawDiff): FilesCollection
    {
        $diff = $this->repository->createRawDiff($rawDiff);

        return $this->parseFilesFromDiff($diff);
    }

    private function parseFilesFromDiff(Diff $diff): FilesCollection
    {
        $files = [];
        /** @var File $file */
        foreach ($diff->getFiles() as $file) {
            $fileObject = $this->makeFileRelativeToProjectDir($file);
            if ($file->isDeletion() || !$this->filesystem->exists($fileObject->getPathname())) {
                continue;
            }

            $files[] = $fileObject;
        }

        return new FilesCollection($files);
    }

    private function makeFileRelativeToProjectDir(File $file): SplFileInfo
    {
        $filePath = $this->paths->makePathRelativeToProjectDir(
            $file->isRename() ? $file->getNewName() : $file->getName()
        );

        return new SplFileInfo($filePath);
    }
}
