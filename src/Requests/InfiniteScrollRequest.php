<?php

namespace HassanDomeDenea\HddLaravelHelpers\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InfiniteScrollRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['nullable', Rule::when($this->boolean('multiple_ids'), ['array'], ['string'])],
            'offset' => 'integer|gte:0',
            'only_id' => 'boolean',
            'multiple_ids' => 'boolean',
            'limit' => 'integer:gte:1',
            'date' => 'date',
            'id_field' => 'nullable|string',
            'order_by' => 'nullable|string',
            'order_direction' => 'nullable|in:asc,desc',
        ];
    }
}
