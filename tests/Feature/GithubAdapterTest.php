<?php

use Atomicptr\FlysystemGithub\PublicUrlCdn;
use League\Flysystem\Config;
use League\Flysystem\UnableToReadFile;

test('fileExists: README.md does exist', function () {
    $adapter = createAdapter();
    expect($adapter->fileExists('README.md'))->toBeTrue();
});

test('fileExists: src/main.rs does exist', function () {
    $adapter = createAdapter();
    expect($adapter->fileExists('src/main.rs'))->toBeTrue();
});

test('fileExists: HITLIST.md does not exists', function () {
    $adapter = createAdapter();
    expect($adapter->fileExists('HITLIST.md'))->toBeFalse();
});

test('read: README.md', function () {
    $adapter = createAdapter();
    $content = $adapter->read('README.md');
    expect($content)
        ->toBeString()
        ->and($content)
        ->toStartWith('# demo-storage');
});

test('read: secret/hitlist.exe throws', function () {
    $adapter = createAdapter();
    expect($adapter->read('secret/hitlist.exe'));
})->throws(UnableToReadFile::class);

test('write/delete: create file src/test.rs, read it and delete it again', function () {
    $adapter = createAdapter();

    if ($adapter->fileExists('src/test.rs')) {
        $adapter->delete('src/test.rs');
    }

    $adapter->write('src/test.rs', 'This is a test', new Config());
    $data = $adapter->read('src/test.rs');
    expect($data)->toBe('This is a test');

    // test if updates work
    $adapter->write('src/test.rs', 'This is a new test', new Config());
    $data = $adapter->read('src/test.rs');
    expect($data)->toBe('This is a new test');

    $adapter->delete('src/test.rs');
    expect($adapter->fileExists('src/test.rs'))->toBeFalse();
});

test('directoryExists: src directory exists', function () {
    $adapter = createAdapter();
    expect($adapter->directoryExists('src'))->toBeTrue();
});

test('directoryExists: target directory does not exists', function () {
    $adapter = createAdapter();
    expect($adapter->directoryExists('target'))->toBeFalse();
});

test('directoryExists: src/main.rs is not a directory', function () {
    $adapter = createAdapter();
    expect($adapter->directoryExists('src/main.rs'))->toBeFalse();
});

test('lastModified: happened after the first of 2024', function () {
    $adapter = createAdapter();
    $attributes = $adapter->lastModified('src/main.rs');
    $lastMod = $attributes->lastModified();
    expect($lastMod)->not()->toBe(null)
        ->and($lastMod)->toBeGreaterThan((new \DateTime('2024-01-01'))->getTimestamp());
});

test('listContents: list contents of src', function () {
    $adapter = createAdapter();

    $count = 0;
    $foundMainRs = false;

    foreach ($adapter->listContents('', true) as $item) {
        $count++;

        if ($item->path() === 'src/main.rs') {
            $foundMainRs = true;
        }
    }

    expect($foundMainRs)->toBeTrue()->and($count)->toBeGreaterThanOrEqual(6);
});

test('createDirectory/deleteDirectory: create and delete secret directory', function () {
    $adapter = createAdapter();

    if ($adapter->directoryExists('secret')) {
        $adapter->deleteDirectory('secret');
    }

    $adapter->createDirectory('secret', new Config());
    expect($adapter->directoryExists('secret'))->toBeTrue();

    $adapter->createDirectory('secret/inner-chamber', new Config());
    expect($adapter->directoryExists('secret/inner-chamber'))->toBeTrue();

    $adapter->write('secret/inner-chamber/chamber', 'This is a test', new Config());

    $adapter->deleteDirectory('secret');
    expect($adapter->directoryExists('secret'))->toBeFalse();
});

test('mimeType: for README.md is text/markdown', function () {
    $adapter = createAdapter();
    expect($adapter->mimeType('README.md')->mimeType())->toBe('text/markdown');
});

test('fileSize: for src/main.rs is exactly 52', function () {
    $adapter = createAdapter();
    expect($adapter->fileSize('src/main.rs')->fileSize())->toBe(52);
});

test('publicUrl: creates a jsdelivr url', function () {
    $adapter = createAdapter();
    $url = $adapter->publicUrl('src/main.rs', new Config());
    expect($url)->toBe('https://cdn.jsdelivr.net/gh/atomicptr/demo-storage@master/src/main.rs');

    $client = new \GuzzleHttp\Client();
    $response = $client->head($url);
    expect($response->getStatusCode())->toBe(200);
});

test('publicUrl: creates a github cdn url', function () {
    $adapter = createAdapter();
    $url = $adapter->publicUrl('src/main.rs', new Config(['publicUrlCdn' => PublicUrlCdn::GithubRaw]));
    expect($url)->toBe('https://raw.githubusercontent.com/atomicptr/demo-storage/master/src/main.rs');

    $client = new \GuzzleHttp\Client();
    $response = $client->head($url);
    expect($response->getStatusCode())->toBe(200);
});

test('move: can move wrong_path.rs to src/wrong_path.rs', function () {
    $adapter = createAdapter();

    if ($adapter->fileExists('src/wrong_path.rs')) {
        $adapter->delete('src/wrong_path.rs');
    }

    $adapter->write('wrong_path.rs', 'This is a test', new Config());

    expect($adapter->fileExists('wrong_path.rs'))->toBeTrue()
        ->and($adapter->fileExists('src/wrong_path.rs'))->toBeFalse();

    $adapter->move('wrong_path.rs', 'src/wrong_path.rs', new Config());

    expect($adapter->fileExists('wrong_path.rs'))->toBeFalse()
        ->and($adapter->fileExists('src/wrong_path.rs'))->toBeTrue();

    $adapter->delete('src/wrong_path.rs');
});

test('copy: copy file src/main.rs to src/main2.rs', function () {
    $adapter = createAdapter();

    if ($adapter->fileExists('src/main2.rs')) {
        $adapter->delete('src/main2.rs');
    }

    $adapter->copy('src/main.rs', 'src/main2.rs', new Config());

    expect($adapter->fileExists('src/main2.rs'))->toBeTrue();

    $dataMain = $adapter->read('src/main.rs');
    $dataMain2 = $adapter->read('src/main.rs');
    expect($dataMain2)->toBe($dataMain);

    $adapter->delete('src/main2.rs');
});
