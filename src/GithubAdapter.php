<?php

namespace Atomicptr\FlysystemGithub;

use Github\Api\Repository\Contents;
use Github\Client;
use Github\Exception\RuntimeException;
use InvalidArgumentException;
use League\Flysystem\Config;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\PathPrefixer;
use League\Flysystem\StorageAttributes;
use League\Flysystem\UnableToCheckDirectoryExistence;
use League\Flysystem\UnableToCheckExistence;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToListContents;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToSetVisibility;
use League\Flysystem\UnableToWriteFile;
use League\Flysystem\UrlGeneration\PublicUrlGenerator;
use League\MimeTypeDetection\ExtensionMimeTypeDetector;
use Throwable;

class GithubAdapter implements FilesystemAdapter, PublicUrlGenerator
{
    protected PathPrefixer $prefixer;

    protected ExtensionMimeTypeDetector $mimeTypeDetector;

    public function __construct(
        protected string $username,
        protected string $repository,
        protected ?Credentials $credentials = null,
        protected ?string $branch = null,
        protected ?Client $githubClient = null,
        protected ?Committer $committer = null,
        string $prefix = '',
    ) {
        $this->githubClient ??= new Client();
        $this->committer ??= new Committer(
            'github-actions[bot]',
            'github-actions[bot]@users.noreply.github.com',
        );
        $this->prefixer = new PathPrefixer($prefix, DIRECTORY_SEPARATOR);
        $this->mimeTypeDetector = new ExtensionMimeTypeDetector();

        $this->credentials ??= Credentials::public();
        $this->credentials->authenticate($this->githubClient);
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
        } catch (RuntimeException $e) {
            if ($e->getMessage() === 'Not Found') {
                return false;
            }
            throw UnableToCheckDirectoryExistence::forLocation($path, $e);
        } catch (Throwable $e) {
            throw UnableToCheckDirectoryExistence::forLocation($path, $e);
        }
    }

    public function deleteDirectory(string $path): void
    {
        try {
            $files = $this->listContents($this->prefixer->prefixPath($path), false);

            foreach ($files as $file) {
                if ($file->type() === StorageAttributes::TYPE_DIRECTORY) {
                    $this->deleteDirectory($file->path());

                    continue;
                }

                $this->delete($file->path());
            }
        } catch (Throwable $e) {
            throw UnableToDeleteDirectory::atLocation($path, $e->getMessage(), $e);
        }
    }

    public function createDirectory(string $path, Config $config): void
    {
        $path = rtrim($path, '/').'/.gitkeep';

        try {
            $this->write($this->prefixer->prefixPath($path), '', $config);
        } catch (Throwable $e) {
            throw new UnableToCreateDirectory($path, $e);
        }
    }

    public function setVisibility(string $path, string $visibility): void
    {
        throw new UnableToSetVisibility('Github API does not support visibility.');
    }

    public function visibility(string $path): FileAttributes
    {
        throw new UnableToSetVisibility('Github API does not support visibility.');
    }

    public function mimeType(string $path): FileAttributes
    {
        $mimeType = $this->mimeTypeDetector->detectMimeTypeFromPath($this->prefixer->prefixPath($path));

        if ($mimeType === null) {
            throw UnableToRetrieveMetadata::mimeType($path);
        }

        return new FileAttributes($path, null, null, null, $mimeType);
    }

    public function lastModified(string $path): FileAttributes
    {
        try {
            $commits = $this->githubClient->api('repo')->commits()->all(
                $this->username,
                $this->repository,
                [
                    'sha' => $this->branch,
                    'path' => $this->prefixer->prefixPath($path),
                ]
            );

            if (empty($path)) {
                throw new RuntimeException("File: $path could not be found");
            }

            $commit = $commits[array_key_first($commits)];

            $lastModified = new \DateTime($commit['commit']['committer']['date']);

            return new FileAttributes(
                $path,
                null,
                null,
                $lastModified->getTimestamp(),
                $this->mimeTypeDetector->detectMimeTypeFromPath($path)
            );
        } catch (Throwable $e) {
            throw UnableToRetrieveMetadata::lastModified($path, $e);
        }
    }

    public function fileSize(string $path): FileAttributes
    {
        try {
            $fileInfo = $this->contents()->show(
                $this->username,
                $this->repository,
                $this->prefixer->prefixPath($path),
                $this->branch,
            );

            return new FileAttributes(
                $path,
                $fileInfo['size'],
                null,
                null,
                $this->mimeTypeDetector->detectMimeTypeFromPath($path)
            );
        } catch (Throwable $e) {
            throw UnableToRetrieveMetadata::fileSize($path, $e);
        }
    }

    public function listContents(string $path, bool $deep): iterable
    {
        try {
            $fileInfo = $this->contents()->show(
                $this->username,
                $this->repository,
                $this->prefixer->prefixPath($path),
                $this->branch,
            );

            if (empty($fileInfo)) {
                return [];
            }

            if (! array_is_list($fileInfo)) {
                throw new RuntimeException('Invalid result, not a list');
            }

            foreach ($fileInfo as $item) {
                $isDirectory = $item['type'] === 'dir';

                if ($isDirectory) {
                    yield new DirectoryAttributes($item['path'], null, null);

                    if (! $deep) {
                        continue;
                    }

                    foreach ($this->listContents($item['path'], true) as $recursiveItem) {
                        yield $recursiveItem;
                    }

                    continue;
                }

                yield new FileAttributes(
                    $item['path'],
                    $this->fileSize($item['path'])->fileSize(),
                    null,
                    $this->lastModified($item['path'])->lastModified(),
                    $this->mimeTypeDetector->detectMimeTypeFromPath($item['path']),
                );
            }
        } catch (Throwable $e) {
            throw UnableToListContents::atLocation($path, $e->getMessage(), $e);
        }
    }

    public function move(string $source, string $destination, Config $config): void
    {
        try {
            $this->copy($source, $destination, $config);
            $this->delete($source);
        } catch (Throwable $e) {
            throw UnableToMoveFile::because($e->getMessage(), $source, $destination);
        }
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        try {
            $content = $this->read($source);
            $this->write($destination, $content, $config);
        } catch (Throwable $e) {
            throw UnableToCopyFile::because($e->getMessage(), $source, $destination);
        }
    }

    /** View rate limits of your api usage */
    public function rateLimits(): array
    {
        return $this->githubClient->api('rate_limit')->getResources();
    }

    /**
     * Provides a public url for the given path.
     *
     * @param  Config  $config  Allows you to specify which CDN to use @see \Atomicptr\Flysystem\PublicUrlCdn
     */
    public function publicUrl(string $path, Config $config): string
    {
        $prefixedPath = $this->prefixer->prefixPath($path);
        $cdn = $config->get('publicUrlCdn', PublicUrlCdn::JsDelivr);

        switch ($cdn) {
            case PublicUrlCdn::JsDelivr:
                $branch = $this->branch ? "@{$this->branch}" : '';

                return "https://cdn.jsdelivr.net/gh/{$this->username}/{$this->repository}$branch/{$prefixedPath}";
            case PublicUrlCdn::GithubRaw:
                if (! $this->branch) {
                    throw new InvalidArgumentException('Github CDN does not allow usage without branch');
                }

                return "https://raw.githubusercontent.com/{$this->username}/{$this->repository}/{$this->branch}/{$prefixedPath}";
            default:
                throw new InvalidArgumentException("Unknown publicUrlCdn: $cdn");
        }
    }
}
