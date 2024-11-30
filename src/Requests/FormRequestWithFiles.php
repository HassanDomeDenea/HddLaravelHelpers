<?php

namespace HassanDomeDenea\HddLaravelHelpers\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FormRequestWithFiles extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $data = json_decode($this->input('_serialized_data', ''), true);
        $this->merge($data);
    }
}
