<?php

namespace App\Services;

use App\Support\OverdueFilters;
use App\Support\OverdueStatsSql;
use Illuminate\Support\Facades\DB;

/** أرقام لوحة التحكم (قراءة فقط من view المتأخرات)، مع فلاتر الفترة والمركز. */
class OverdueStats
{
    /** @return array{installments: int, citizens: int, total: float} */
    public function totals(?OverdueFilters $filters = null): array
    {
        [$where, $bindings] = ($filters ?? new OverdueFilters())->where();
        $row = DB::selectOne(OverdueStatsSql::totals($where), $bindings);

        return [
            'installments' => (int) $row->installments,
            'citizens' => (int) $row->citizens,
            'total' => (float) $row->total,
        ];
    }

    /** @return list<array{label: string, amount: float, count: int}> */
    public function aging(?OverdueFilters $filters = null): array
    {
        [$where, $bindings] = ($filters ?? new OverdueFilters())->where();
        $row = (array) DB::selectOne(OverdueStatsSql::aging($where), $bindings);

        return array_map(fn (array $bucket): array => [
            'label' => $bucket[3],
            'amount' => (float) ($row[$bucket[0] . '_amount'] ?? 0),
            'count' => (int) ($row[$bucket[0] . '_count'] ?? 0),
        ], OverdueStatsSql::BUCKETS);
    }

    /** @return list<array{label: string, count: int, total: float}> */
    public function byPlan(?OverdueFilters $filters = null): array
    {
        [$where, $bindings] = ($filters ?? new OverdueFilters())->where();

        return array_map(fn ($row): array => [
            'label' => (string) $row->payment_plan_name,
            'count' => (int) $row->installments,
            'total' => (float) $row->total,
        ], DB::select(OverdueStatsSql::byPlan($where), $bindings));
    }

    /**
     * صفوف جاهزة لجدول Filament (records): مفتاح الصف = center_id (0 = بدون مركز).
     *
     * @return array<int, array{center_id: ?int, center_name: string, citizens: int, installments: int, total: float, share: float}>
     */
    public function byCenter(?OverdueFilters $filters = null): array
    {
        [$where, $bindings] = ($filters ?? new OverdueFilters())->where();
        $rows = DB::select(OverdueStatsSql::byCenter($where), $bindings);

        $grandTotal = array_sum(array_map(fn ($row): float => (float) $row->total, $rows));

        $result = [];
        foreach ($rows as $row) {
            $result[(int) ($row->center_id ?? 0)] = [
                'center_id' => $row->center_id === null ? null : (int) $row->center_id,
                'center_name' => (string) $row->center_name,
                'citizens' => (int) $row->citizens,
                'installments' => (int) $row->installments,
                'total' => (float) $row->total,
                'share' => $grandTotal > 0 ? round(((float) $row->total / $grandTotal) * 100, 1) : 0.0,
            ];
        }

        return $result;
    }

    /**
     * صفوف جاهزة لجدول Filament (records): مفتاح الصف = citizen_id.
     *
     * @return array<int, array<string, mixed>>
     */
    public function topCitizens(int $limit = 10, ?OverdueFilters $filters = null): array
    {
        [$where, $bindings] = ($filters ?? new OverdueFilters())->where();

        $rows = [];
        foreach (DB::select(OverdueStatsSql::topCitizens($limit, $where), $bindings) as $row) {
            $rows[(int) $row->citizen_id] = (array) $row;
        }

        return $rows;
    }
}
