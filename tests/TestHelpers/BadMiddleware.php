<?php

namespace HassanDomeDenea\HddLaravelHelpers\Tests\TestHelpers;


use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class BadMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        throw new \Exception("Bad Middleware");
        return $next($request);
    }
}
