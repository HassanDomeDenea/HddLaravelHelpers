<?php

namespace HassanDomeDenea\HddLaravelHelpers\Helpers;

use HassanDomeDenea\HddLaravelHelpers\Data\AuditData;
use HassanDomeDenea\HddLaravelHelpers\Data\AuditUserData;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use OwenIt\Auditing\Models\Audit;

class AuditableUtilities
{
    public static function FormatAuditQuery(Builder|MorphMany $query, string $field, bool $withAllValues = false): Paginator
    {
        $auditQuery = $query
            ->where(function ($query) use ($field) {
                $query->whereNotNull("old_values->" . $field)
                    ->orWhereNotNull("new_values->" . $field);
            })
            ->select(['id', 'event', 'old_values', 'new_values', 'created_at', 'user_type', 'user_id', 'auditable_type', 'auditable_id'])
            ->orderByDesc('created_at');

        $perPage = request()->integer('per_page', -1);
        $pageNumber = request()->integer('page', 1);
        if ($perPage === -1) {
            $perPage = $auditQuery->count();
            $pageNumber = 1;
        }
        $paginatedData = $auditQuery->simplePaginate($perPage, page: $pageNumber);
        $paginatedData->getCollection()->transform(function (Audit $audit) use ($field, $withAllValues) {
            if ($withAllValues) {
                return new AuditData(
                    id: $audit->id,
                    event: $audit->event,
                    oldValue: data_get($audit->old_values, $field),
                    newValue: data_get($audit->old_values, $field),
                    createdAt: $audit->created_at,
                    user: $audit->user ? AuditUserData::from($audit->user) : null,
                    oldValues: $audit->old_values,
                    newValues: $audit->new_values,
                    auditableType: $audit->auditable_type,
                    auditableId: $audit->auditable_id
                );
            } else {
                return new AuditData(
                    id: $audit->id,
                    event: $audit->event,
                    oldValue: data_get($audit->old_values, $field),
                    newValue: data_get($audit->old_values, $field),
                    createdAt: $audit->created_at,
                    user: $audit->user ? AuditUserData::from($audit->user) : null,
                    auditableType: $audit->auditable_type,
                    auditableId: $audit->auditable_id
                );
            }
        });
        return $paginatedData;
    }
}
