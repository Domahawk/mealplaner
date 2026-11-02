<?php

// FastCGI ping checks the running FPM pool rather than an unrelated CLI process.
$socket = @fsockopen('127.0.0.1', 9000, $errorCode, $errorMessage, 2);
if ($socket === false) {
    exit(1);
}

function record(int $type, string $content): string
{
    return pack('CCnnCC', 1, $type, 1, strlen($content), 0, 0).$content;
}

$params = '';
foreach (['REQUEST_METHOD' => 'GET', 'SCRIPT_NAME' => '/fpm-ping', 'SCRIPT_FILENAME' => '/fpm-ping'] as $key => $value) {
    $params .= chr(strlen($key)).chr(strlen($value)).$key.$value;
}
fwrite($socket, record(1, pack('nCxxxxx', 1, 0)).record(4, $params).record(4, '').record(5, ''));
stream_set_timeout($socket, 2);
$response = stream_get_contents($socket);
fclose($socket);
exit(is_string($response) && str_contains($response, 'pong') ? 0 : 1);
