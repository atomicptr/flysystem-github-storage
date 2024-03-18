<?php

namespace Atomicptr\FlysystemGithub;

use Github\Api\Repository\Contents;
use Github\Client;
use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\PathPrefixer;
use League\Flysystem\UnableToCheckDirectoryExistence;
use League\Flysystem\UnableToCheckExistence;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToWriteFile;
use League\MimeTypeDetection\ExtensionMimeTypeDetector;
use Throwable;

class GithubAdapter implements FilesystemAdapter
{
    protected PathPrefixer $prefixer;

    protected ExtensionMimeTypeDetector $mimeTypeDetector;

    public function __construct(
        protected string $username,
        protected string $repository,
        protected ?string $branch = null,
        protected ?Client $githubClient = null,
        protected ?Committer $committer = null,
        string $prefix = '',
    ) {
        $this->githubClient ??= new Client();
        $this->committer ??= new Committer(
            'atomicptr/flysystem-github-storage[bot]',
            'github-actions[bot]@users.noreply.github.com', // TODO: add custom email
        );
        $this->prefixer = new PathPrefixer($prefix, DIRECTORY_SEPARATOR);
        $this->mimeTypeDetector = new ExtensionMimeTypeDetector();
    }

    private function contents(): Contents
    {
        return $this->githubClient->api('repo')->contents();
    }

    public function fileExists(string $path): bool
    {
        try {
            return $this->contents()->exists($this->username, $this->repository, $this->prefixer->prefixPath($path));
        } catch (Throwable $e) {
            throw UnableToCheckExistence::forLocation($path, $e);
        }
    }

    public function write(string $path, string $contents, Config $config): void
    {
        try {
            $prefixedPath = $this->prefixer->prefixPath($path);

            if (! $this->fileExists($prefixedPath)) {
                $this->contents()->create(
                    $this->username,
                    $this->repository,
                    $prefixedPath,
                    $contents,
                    "Created file: $prefixedPath",
                    $this->branch,
                    $this->committer->toArray(),
                );

                return;
            }

            $oldFile = $this->contents()->show(
                $this->username,
                $this->repository,
                $prefixedPath,
                $this->branch,
            );

            $this->contents()->update(
                $this->username,
                $this->repository,
                $prefixedPath,
                $contents,
                "Updated file: $prefixedPath",
                $oldFile['sha'],
                $this->branch,
                $this->committer->toArray(),
            );
        } catch (Throwable $e) {
            throw UnableToWriteFile::atLocation($path, $e->getMessage(), $e);
        }
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        // TODO: check if this actually works lol...
        $this->write($path, $contents, $config);
    }

    public function read(string $path): string
    {
        try {
            $fileInfo = $this->contents()->show(
                $this->username,
                $this->repository,
                $this->prefixer->prefixPath($path),
                $this->branch,
            );

            if (is_string($fileInfo)) {
                return $fileInfo;
            }

            if ($fileInfo['encoding'] === 'base64') {
                return base64_decode($fileInfo['content']);
            }

            return match ($fileInfo['encoding']) {
                'base64' => base64_decode($fileInfo['content']),
                default => throw new \RuntimeException("Unknown encoding: $fileInfo[encoding]"),
            };
        } catch (Throwable $e) {
            throw UnableToReadFile::fromLocation($path, $e->getMessage(), $e);
        }
    }

    public function readStream(string $path)
    {
        // TODO: check if this actually works lol...
        return $this->read($path);
    }

    public function delete(string $path): void
    {
        try {
            $prefixedPath = $this->prefixer->prefixPath($path);

            $oldFile = $this->contents()->show(
                $this->username,
                $this->repository,
                $prefixedPath,
                $this->branch,
            );

            $this->contents()->rm(
                $this->username,
                $this->repository,
                $prefixedPath,
                "Deleted file: $prefixedPath",
                $oldFile['sha'],
                $this->branch,
                $this->committer->toArray(),
            );
        } catch (Throwable $e) {
            throw UnableToDeleteFile::atLocation($path, $e->getMessage(), $e);
        }
    }

    public function directoryExists(string $path): bool
    {
        try {
            $fileInfo = $this->contents()->show(
                $this->username,
                $this->repository,
                $this->prefixer->prefixPath($path),
                $this->branch,
            );

            return is_array($fileInfo) && ! empty($fileInfo) && array_is_list($fileInfo);
        } catch (Throwable $e) {
            throw UnableToCheckDirectoryExistence::forLocation($path, $e);
        }
    }

    public function deleteDirectory(string $path): void
    {
        // TODO: Implement deleteDirectory() method.
    }

    public function createDirectory(string $path, Config $config): void
    {
        // TODO: Implement createDirectory() method.
    }

    public function setVisibility(string $path, string $visibility): void
    {
        // TODO: Implement setVisibility() method.
    }

    public function visibility(string $path): FileAttributes
    {
        // TODO: Implement visibility() method.
    }

    public function mimeType(string $path): FileAttributes
    {
        // TODO: Implement mimeType() method.
    }

    public function lastModified(string $path): FileAttributes
    {
        // TODO: Implement lastModified() method.
    }

    public function fileSize(string $path): FileAttributes
    {
        // TODO: Implement fileSize() method.
    }

    public function listContents(string $path, bool $deep): iterable
    {
        // TODO: Implement listContents() method.
    }

    public function move(string $source, string $destination, Config $config): void
    {
        // TODO: Implement move() method.
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        // TODO: Implement copy() method.
    }
}
