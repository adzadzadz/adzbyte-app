<?php

// Acquire before Composer loads: no request may execute across an in-place release.
$deploymentLock = @fopen(__DIR__.'/../storage/framework/deployment.lock', 'c');
if ($deploymentLock === false || ! flock($deploymentLock, LOCK_SH | LOCK_NB)) {
    http_response_code(503);
    header('Retry-After: 60');
    header('Cache-Control: no-store');
    echo 'A scheduled update is in progress. Please try again in a minute.';
    exit;
}
// Keep the resource alive through all later shutdown callbacks. PHP closes it
// when the request is torn down; an early unlock could race with Laravel cleanup.
