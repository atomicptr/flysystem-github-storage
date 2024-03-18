# flysystem-github-storage

A GitHub based filesystem for Flysystem, powered by [php-github-api](https://github.com/KnpLabs/php-github-api).

**Note**: Keep in mind that GitHub has a rate limit, so if you need a lot of file operations you might
need something else.

Inspired by [@RoyVoetman/flysystem-gitlab-storage](https://github.com/RoyVoetman/flysystem-gitlab-storage).

## Usage

```php
<?php

use Atomicptr\FlysystemGithub\GithubAdapter;
use Atomicptr\FlysystemGithub\Credentials;
use Atomicptr\FlysystemGithub\Committer;
use League\Flysystem\Filesystem;

$adapter = new GithubAdapter(
    "username",
    "repository",
    branch: "master",
    credentials: Credentials::fromToken("token..."),
    committer: new Committer("Peter Developer", "peter@developer.tld"),
);

$filesystem = new Filesystem($adapter);

// see http://flysystem.thephpleague.com/api/ for full list of available functionality
```

### Laravel

Check out my other repository: [atomicptr/laravel-github-storage](https://github.com/atomicptr/laravel-github-storage)

## License

MIT