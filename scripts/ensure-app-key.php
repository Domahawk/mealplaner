<?php

require __DIR__.'/../vendor/autoload.php';

$path = __DIR__.'/../.env';
$handle = fopen($path, 'c+');

if ($handle === false || ! flock($handle, LOCK_EX)) {
    throw new RuntimeException('Cannot lock .env. Check project file ownership.');
}

try {
    $contents = stream_get_contents($handle);
    if ($contents === false) {
        throw new RuntimeException('Cannot read .env.');
    }

    $values = Dotenv\Dotenv::parse($contents);
    if (($values['APP_KEY'] ?? '') !== '') {
        exit(0);
    }

    $key = 'APP_KEY=base64:'.base64_encode(random_bytes(32));
    $updated = preg_match('/^APP_KEY\s*=/m', $contents) === 1
        ? preg_replace('/^APP_KEY\s*=.*$/m', $key, $contents)
        : rtrim($contents)."\n".$key."\n";

    if ($updated === null) {
        throw new RuntimeException('Cannot update APP_KEY.');
    }

    rewind($handle);
    if (fwrite($handle, $updated) === false || ! ftruncate($handle, strlen($updated))) {
        throw new RuntimeException('Cannot write APP_KEY. Check .env permissions.');
    }
    fflush($handle);
    echo "Initialized the local application key.\n";
} finally {
    flock($handle, LOCK_UN);
    fclose($handle);
}
