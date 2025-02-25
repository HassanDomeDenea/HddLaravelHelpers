<?php

declare(strict_types=1);

namespace HassanDomeDenea\HddLaravelHelpers\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class BatchRequestRequest extends FormRequest
{
    /**
     * @return array<string,string|array<string>>
     */
    public function rules(): array
    {
        return [
            'requests' => ['required','array'],
            'requests.*.method' => 'required|string|in:GET,POST,PUT,PATCH,DELETE',
            'requests.*.url' => ['string', 'required'],
            'requests.*.body' => 'nullable|array',
        ];
    }
}
