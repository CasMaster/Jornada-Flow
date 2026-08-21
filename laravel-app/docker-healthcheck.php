<?php

$prefix = trim((string) getenv('APP_ROUTE_PREFIX'), '/');
$path = ($prefix === '' ? '' : '/'.$prefix).'/health/ready';
$context = stream_context_create(['http' => ['timeout' => 4, 'ignore_errors' => true]]);
$response = @file_get_contents('http://127.0.0.1'.$path, false, $context);
$statusLine = $http_response_header[0] ?? '';

exit($response !== false && str_contains($statusLine, ' 200 ') ? 0 : 1);
