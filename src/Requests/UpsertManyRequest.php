<?php

namespace HassanDomeDenea\HddLaravelHelpers\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpsertManyRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'data' => 'required|array',
            'data.*' => 'array',
            'data.*.id' => 'nullable',
        ];
    }
}
