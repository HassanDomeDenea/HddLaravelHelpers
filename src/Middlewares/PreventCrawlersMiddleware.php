<?php

namespace HassanDomeDenea\HddLaravelHelpers\Middlewares;

use Closure;
use Illuminate\Http\Request;
use Jaybizzle\CrawlerDetect\CrawlerDetect;
use Symfony\Component\HttpFoundation\Response;

class PreventCrawlersMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $CrawlerDetect = new CrawlerDetect;
        abort_if($CrawlerDetect->isCrawler(),401,"Bots are unauthorized");
        return $next($request);

}
}
