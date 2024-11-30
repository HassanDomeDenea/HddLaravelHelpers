<?php

namespace HassanDomeDenea\HddLaravelHelpers\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DestroyManyRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'ids' => 'array',
        ];
    }
}
