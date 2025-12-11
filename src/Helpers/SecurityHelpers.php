<?php

namespace HassanDomeDenea\HddLaravelHelpers\Helpers;

use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;

class SecurityHelpers
{
    /**
     * Authenticate user using bearer token from cookie
     *
     * @param string $cookieName
     * @param string|null $guard
     * @return bool
     */
    public static function AuthenticateFromCookie(string $cookieName = 'authorizationToken', ?string $guard= null): bool
    {
        if (auth($guard)->check()) {
            return true;
        }

        $bearerToken = Cookie::get($cookieName);
        if ($bearerToken) {
            request()->headers->set('Authorization', 'Bearer ' . $bearerToken);
            return auth($guard)->check();
        }
        return false;
    }
}
