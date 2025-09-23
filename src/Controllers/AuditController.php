<?php

namespace HassanDomeDenea\HddLaravelHelpers\Controllers;

use HassanDomeDenea\HddLaravelHelpers\Data\AuditData;
use HassanDomeDenea\HddLaravelHelpers\Data\AuditUserData;
use HassanDomeDenea\HddLaravelHelpers\Helpers\ApiResponse;
use HassanDomeDenea\HddLaravelHelpers\Helpers\AuditableUtilities;
use Illuminate\Http\Request;
use OwenIt\Auditing\Models\Audit;

class AuditController
{
    public function index(Request $request): ApiResponse
    {
        $request->validate([
            'type' => ['required'],
            'id' => ['required'],
            'field' => ['required', 'string'],
            'with_all_values' => ['boolean'],
        ]);

        return ApiResponse::successResponse(
            AuditableUtilities::FormatAuditQuery(
                Audit::query()
                    ->where('auditable_type', $request->input('type'))
                    ->where('auditable_id', $request->input('id')),
                $request->input('field'),
                $request->boolean('with_all_values')
            ),
        );

    }
}
