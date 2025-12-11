<?php

namespace HassanDomeDenea\HddLaravelHelpers\Middlewares;

use Closure;
use HassanDomeDenea\HddLaravelHelpers\Helpers\SecurityHelpers;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateFromCookieMiddleware
{

    public static string $cookieName = 'authorizationToken';
    public function handle(Request $request, Closure $next): Response
    {
        SecurityHelpers::AuthenticateFromCookie(static::$cookieName);

        return $next($request);
    }
}
