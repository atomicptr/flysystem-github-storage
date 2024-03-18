<?php

// fileExists tests...
use League\Flysystem\UnableToReadFile;

test('fileExists: README.md does exist', function () {
    $adapter = createAdapter();
    expect($adapter->fileExists('README.md'))->toBeTrue();
});

test('fileExists: src/app.html does exist', function () {
    $adapter = createAdapter();
    expect($adapter->fileExists('src/app.html'))->toBeTrue();
});

test('fileExists: HITLIST.md does not exists', function () {
    $adapter = createAdapter();
    expect($adapter->fileExists('HITLIST.md'))->toBeFalse();
});

// read tests...
test('read: README.md', function () {
    $adapter = createAdapter();
    $content = $adapter->read('README.md');
    expect($content)
        ->toBeString()
        ->and($content)
        ->toStartWith('# atomicptr.dev');
});

test('read: secret/hitlist.exe throws', function () {
    $adapter = createAdapter();
    expect($adapter->read('secret/hitlist.exe'));
})->throws(UnableToReadFile::class);

// directoryExists tests..
test('directoryExists: src directory exists', function () {
    $adapter = createAdapter();
    expect($adapter->directoryExists('src'))->toBeTrue();
});

// TODO: tests to implement...
//write
//writeStream
//readStream
//delete
//directoryExists
//deleteDirectory
//createDirectory
//setVisibility
//visibility
//mimeType
//lastModified
//fileSize
//listContents
//move
//copy
