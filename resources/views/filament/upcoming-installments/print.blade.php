<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>طباعة الأقساط القادمة</title>
    <link rel="stylesheet" href="{{ asset('css/kfs/fonts.css') }}">
    <style>
        body {
            margin: 24px;
            font-family: Tajawal, Arial, sans-serif;
            color: #111827;
            background: #fff;
        }

        .toolbar {
            display: flex;
            justify-content: flex-start;
            margin-bottom: 16px;
        }

        .toolbar button {
            padding: 8px 18px;
            border: 1px solid #374151;
            border-radius: 6px;
            background: #374151;
            color: #fff;
            font: inherit;
            cursor: pointer;
        }

        h1 {
            margin: 0 0 6px;
            font-size: 18pt;
            text-align: center;
        }

        .meta {
            margin: 0 0 14px;
            text-align: center;
            font-size: 10pt;
            color: #374151;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        th, td {
            border: 1px solid #777;
            padding: 5px 4px;
            font-size: 9pt;
            line-height: 1.3;
            text-align: right;
            overflow-wrap: anywhere;
        }

        thead {
            display: table-header-group;
        }

        thead th {
            background: #f3f4f6;
            font-weight: 700;
        }

        tbody tr {
            break-inside: avoid;
            page-break-inside: avoid;
        }

        tfoot td {
            background: #f3f4f6;
            font-weight: 700;
            border-top: 2px solid #111827;
        }

        .empty {
            text-align: center;
            padding: 18px;
        }

        @page {
            size: landscape;
            margin: 10mm;
        }

        @media print {
            body {
                margin: 0;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .toolbar {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">طباعة</button>
    </div>

    <h1>تقرير الأقساط القادمة</h1>

    <p class="meta">
        @if ($centerName)
            <span>المركز: {{ $centerName }}</span>
            &nbsp;|&nbsp;
        @endif
        <span>من: {{ $from ?? 'البداية' }}</span>
        &nbsp;|&nbsp;
        <span>إلى: {{ $to ?? 'النهاية' }}</span>
        @if ($search)
            &nbsp;|&nbsp;
            <span>البحث: {{ $search }}</span>
        @endif
        &nbsp;|&nbsp;
        <span>تاريخ الطباعة: {{ $printedAt }}</span>
    </p>

    <table>
        <thead>
            <tr>
                <th style="width: 11%">رقم العقد</th>
                <th style="width: 16%">اسم المواطن</th>
                <th style="width: 10%">المركز</th>
                <th style="width: 12%">نظام السداد</th>
                <th style="width: 7%">رقم القسط</th>
                <th style="width: 11%">تاريخ الاستحقاق</th>
                <th style="width: 11%">الأيام المتبقية</th>
                <th style="width: 11%">قيمة القسط</th>
                <th style="width: 11%">المبلغ المتبقي</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($records as $record)
                <tr>
                    <td>{{ $record->contract_code }}</td>
                    <td>{{ $record->citizen_name }}</td>
                    <td>{{ $record->center_name }}</td>
                    <td>{{ $record->payment_plan_name }}</td>
                    <td>{{ $record->installment_no }}</td>
                    <td>{{ $record->due_date?->format('Y-m-d') }}</td>
                    <td>
                        {{ (int) $record->days_remaining === 0
                            ? 'مستحق اليوم'
                            : 'متبقي ' . $record->days_remaining . ' يوم' }}
                    </td>
                    <td>{{ number_format((float) $record->amount, 2) }}</td>
                    <td>{{ number_format((float) $record->remaining_amount, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td class="empty" colspan="9">لا توجد أقساط مطابقة للفلاتر المختارة.</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="7">الإجمالي</td>
                <td>{{ number_format($totalAmount, 2) }}</td>
                <td>{{ number_format($totalRemaining, 2) }}</td>
            </tr>
        </tfoot>
    </table>

    <script>
        window.addEventListener('load', function () {
            window.print();
        });
    </script>
</body>
</html>
