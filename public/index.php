<?php

use App\Kernel;
use Symfony\Component\HttpFoundation\Request;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $context) {
    // TLS is terminated by a reverse proxy in production. Trust its forwarded
    // headers when the proxy is known.
    if ($proxies = $context['TRUSTED_PROXIES'] ?? '') {
        Request::setTrustedProxies(
            explode(',', $proxies),
            Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO
        );
    }

    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
