<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Center;
use App\Models\UpcomingInstallment;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class UpcomingInstallmentPrintController extends Controller
{
    private const DAYS_RANGES = ['today', '1_7', '8_15', '10_20', '16_30'];

    public function __invoke(Request $request): View
    {
        $filters = $request->validate([
            'center_id' => 'nullable|integer|min:1',
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d',
            'days_range' => 'nullable|in:'.implode(',', self::DAYS_RANGES),
            'q' => 'nullable|string|max:100',
        ]);

        $centerId = filled($filters['center_id'] ?? null) ? (int) $filters['center_id'] : null;
        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;
        $search = $filters['q'] ?? null;

        if ($from && $to && $from > $to) {
            [$from, $to] = [$to, $from];
        }

        $records = UpcomingInstallment::query()
            ->when($centerId, fn (Builder $query) => $query->where('center_id', $centerId))
            ->when($from, fn (Builder $query) => $query->whereDate('due_date', '>=', $from))
            ->when($to, fn (Builder $query) => $query->whereDate('due_date', '<=', $to))
            ->when($filters['days_range'] ?? null, function (Builder $query, string $range): Builder {
                return match ($range) {
                    'today' => $query->where('days_remaining', 0),
                    '1_7' => $query->whereBetween('days_remaining', [1, 7]),
                    '8_15' => $query->whereBetween('days_remaining', [8, 15]),
                    '10_20' => $query->whereBetween('days_remaining', [10, 20]),
                    '16_30' => $query->whereBetween('days_remaining', [16, 30]),
                    default => $query,
                };
            })
            ->when($search, function (Builder $query) use ($search): void {
                $query->where(function (Builder $inner) use ($search): void {
                    $inner->where('contract_code', 'like', "%{$search}%")
                        ->orWhere('citizen_name', 'like', "%{$search}%");
                });
            })
            ->orderBy('days_remaining', 'asc')
            ->orderBy('due_date', 'asc')
            ->get();

        return view('filament.upcoming-installments.print', [
            'records' => $records,
            'centerName' => $centerId
                ? Center::query()->whereKey($centerId)->value('name')
                : null,
            'from' => $from,
            'to' => $to,
            'search' => $search,
            'printedAt' => now()->format('Y-m-d H:i'),
            'totalAmount' => $records->sum(fn ($record): float => (float) $record->amount),
            'totalRemaining' => $records->sum(fn ($record): float => (float) $record->remaining_amount),
        ]);
    }
}
