<?php

use Atomicptr\FlysystemGithub\Credentials;
use Atomicptr\FlysystemGithub\GithubAdapter;
use Atomicptr\FlysystemGithub\Tests\TestCase;

uses(TestCase::class)->in('Feature');

function createAdapter(string $prefix = ''): GithubAdapter
{
    $token = getenv('GITHUB_TOKEN') ? Credentials::fromToken(getenv('GITHUB_TOKEN')) : null;

    return new GithubAdapter(
        'atomicptr',
        'demo-storage',
        credentials: $token,
        branch: 'master',
        prefix: $prefix,
    );
}
