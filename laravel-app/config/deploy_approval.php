<?php

return [
    'environment' => env('DEPLOY_APPROVAL_ENV', ''),
    'key' => env('DEPLOY_APPROVAL_KEY', ''),
    'pending_dir' => env('DEPLOY_PENDING_DIR', '/run/mixhome-ci/pending'),
    'approved_dir' => env('DEPLOY_APPROVED_DIR', '/run/mixhome-ci/web-approved'),
];
