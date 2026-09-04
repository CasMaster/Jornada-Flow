<?php

return [
    'slow_request_ms' => (int) env('SLOW_REQUEST_MS', 750),
    'slow_query_ms' => (int) env('SLOW_QUERY_MS', 250),
];
