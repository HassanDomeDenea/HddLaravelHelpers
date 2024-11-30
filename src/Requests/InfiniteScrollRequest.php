<?php

namespace HassanDomeDenea\HddLaravelHelpers\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InfiniteScrollRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'nullable|string',
            'offset' => 'integer',
            'onlyId' => 'boolean',
            'limit' => 'integer',
            'date' => 'date',
        ];
    }
}
