<?php


use HassanDomeDenea\HddLaravelHelpers\Whatsapp\WhatsappClient;

it('can build base uri',function (){
    $whatsappClient = new WhatsappClient();
$uri = $whatsappClient->baseUri();
    $uri->withPath('check-number');
    dump($uri->withFragment('check-number')->toHtml());

});
