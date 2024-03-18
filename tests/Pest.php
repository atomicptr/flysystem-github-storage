<?php

use Atomicptr\FlysystemGithub\GithubAdapter;
use Atomicptr\FlysystemGithub\Tests\TestCase;

uses(TestCase::class)->in('Feature');

function createAdapter(): GithubAdapter
{
    return new GithubAdapter(
        'atomicptr',
        'atomicptr.dev',
    );
}
