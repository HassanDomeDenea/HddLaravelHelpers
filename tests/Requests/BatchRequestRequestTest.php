<?php

use HassanDomeDenea\HddLaravelHelpers\Requests\BatchRequestRequest;
use Illuminate\Support\Facades\Validator;

it('requires requests', function () {
    $payload = [];
    $request = new BatchRequestRequest();

    $validator = Validator::make($payload, $request->rules());

    expect($validator->passes())->toBeFalse();

});
