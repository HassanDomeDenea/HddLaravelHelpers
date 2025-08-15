<?php

namespace HassanDomeDenea\HddLaravelHelpers\Whatsapp;

use HassanDomeDenea\HddLaravelHelpers\Whatsapp\Responses\WhatsappApiCheckNumberResponse;
use HassanDomeDenea\HddLaravelHelpers\Whatsapp\Responses\WhatsappFailedResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Uri;
use Throwable;

class WhatsappClient
{
    public function __construct()
    {

    }

    public function baseUri(): Uri
    {
        $userId = config('hdd-laravel-helpers.whatsapp-userid');
        return Uri::of('https://example.com')
            ->withHost(config('hdd-laravel-helpers.whatsapp-hostname', 'whatsapp-bot.hdd-apps.com'))
            ->withScheme('https')
            ->withUser(config('hdd-laravel-helpers.whatsapp-username'), config('hdd-laravel-helpers.whatsapp-password'))
            ->withPath("api/users/$userId/whatsapp");

    }

    public function checkNumber(mixed $phone): WhatsappFailedResponse|WhatsappApiCheckNumberResponse
    {
        $phone = str($phone)->replace('+', '')->ltrim('964')->ltrim('0')->prepend('964')
            ->toString();
        if (strlen($phone) < 12) {
            return new WhatsappFailedResponse(__('Invalid phone number'));
        }

        $uri = $this->baseUri();
        try {
            $response = Http::get($uri->withPath($uri->path() . '/check-number')->withQuery(['phone' => $phone, 'with_contact' => 1]) . '/check-number');
            if ($response->successful()) {
                $json = fluent($response->json());
                return new WhatsappApiCheckNumberResponse($json->boolean('exists'), $json->string('name', $phone));
            } else {
                return new WhatsappFailedResponse(__($response->json('error') ?: $response->json('message') ?: $response->body()));
            }
        } catch (Throwable $throwable) {
            return new WhatsappFailedResponse(__($throwable->getMessage()));
        }
    }
}
