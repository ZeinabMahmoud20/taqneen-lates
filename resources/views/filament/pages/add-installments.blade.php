<x-filament-panels::page>
    <div style="display: flex; flex-direction: column; gap: 1.5rem;">

        {{-- تعليمات سريعة --}}
        <x-filament::callout
            color="info"
            icon="heroicon-o-information-circle"
            heading="الملف المطلوب"
        >
            التقرير لازم يكون بصيغة <strong>xlsx</strong>، وأول شيت بس هو اللي بيتقري.
            صف العناوين رقم <strong>9</strong>، والبيانات من صف <strong>10</strong>.
            الأعمدة اللي بتتعرف: «رقم الطلب»، «تاريخ الإصدار»، «المبلغ»، «تاريخ الأذن»،
            «الاسم»، «التليفون»، «الرقم القومي»، و«م».
            <br>
            <strong>تاريخ الإصدار</strong> = تاريخ السداد الفعلي، و<strong>المبلغ</strong> = قيمة
            القسط المدفوعة (وهو المرجع حتى لو مختلف عن المسجل في النظام). القسط بيتحول لمسدد
            ويختفي من المتأخرات، و<strong>تاريخ الأذن</strong> بيتسجل كتاريخ إضافي على القسط.
            جرّب الأول «تحقق فقط» علشان تشوف النتيجة من غير ما يتحفظ حاجة.
        </x-filament::callout>

        {{-- رفع الملف --}}
        <x-filament::section
            heading="رفع ملف Excel"
            description="اختار ملف التقرير، وبعدين حدد تحقق فقط أو تنفيذ السداد."
        >
            <div style="display: flex; flex-direction: column; gap: 1rem;">
                <div>
                    <label for="installments-file" style="display: block; margin-bottom: .5rem; font-weight: 600;">
                        ملف Excel (xlsx)
                    </label>

                    <x-filament::input.wrapper>
                        <x-filament::input
                            id="installments-file"
                            type="file"
                            accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                            wire:model="file"
                        />
                    </x-filament::input.wrapper>

                    <p wire:loading wire:target="file" style="margin-top: .5rem; font-size: .875rem; color: #626963;">
                        جاري رفع الملف...
                    </p>

                    @error('file')
                        <p style="margin-top: .5rem; font-size: .875rem; color: #b91c1c;">{{ $message }}</p>
                    @enderror
                </div>

                <div style="display: flex; flex-wrap: wrap; gap: .75rem; align-items: center;">
                    <x-filament::button
                        type="button"
                        color="gray"
                        icon="heroicon-o-check-badge"
                        wire:click="run(true)"
                        wire:loading.attr="disabled"
                        wire:target="run"
                    >
                        تحقق فقط (من غير حفظ)
                    </x-filament::button>

                    <x-filament::button
                        type="button"
                        color="primary"
                        icon="heroicon-o-plus-circle"
                        wire:click="run(false)"
                        wire:confirm="هيتم تسجيل سداد الأقساط وتواريخ الأذن فعلاً. العملية مش ليها تراجع تلقائي. تحب تكمل؟"
                        wire:loading.attr="disabled"
                        wire:target="run"
                    >
                        تنفيذ السداد
                    </x-filament::button>

                    <span wire:loading wire:target="run" style="font-size: .875rem; color: #626963;">
                        جاري معالجة الملف...
                    </span>
                </div>

                <p style="font-size: .8125rem; color: #858A84;">
                    ملاحظة: الملف المرفوع بيتشال من السيرفر بعد المعالجة. تقرير الأخطاء والتنبيهات
                    بيتولد تلقائياً وبيظهر زرار تحميله تحت.
                </p>
            </div>
        </x-filament::section>

        {{-- خطأ في الملف نفسه --}}
        @if ($fatalError)
            <x-filament::callout
                color="danger"
                icon="heroicon-o-exclamation-triangle"
                heading="الملف مش مظبوط"
            >
                {{ $fatalError }}
            </x-filament::callout>
        @endif

        {{-- نتيجة آخر تشغيل --}}
        @if ($lastResult)
            <x-filament::section
                heading="{{ $lastResult['dry_run'] ? 'نتيجة التحقق (ماتحفظش حاجة)' : 'نتيجة تسجيل السداد' }}"
                description="{{ $lastResult['summary'] }}"
            >
                <div style="display: flex; flex-direction: column; gap: 1.25rem;">

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: .75rem;">
                        @foreach ([
                            'إجمالي الصفوف' => $lastResult['total'],
                            'صفوف اتعالجت' => $lastResult['applied'],
                            'صفوف اتجاهلت' => $lastResult['skipped'],
                            'أقساط اتسددت' => $lastResult['paid_installments'],
                            'تواريخ أذن اتسجلت' => $lastResult['permits_saved'],
                            'إجمالي المدفوع' => number_format((float) $lastResult['paid_total'], 2),
                        ] as $label => $value)
                            <div style="border: 1px solid var(--line); border-radius: var(--r); padding: .875rem; background: var(--papyrus);">
                                <div style="font-size: .75rem; color: var(--text-soft); margin-bottom: .35rem;">{{ $label }}</div>
                                <div style="font-size: 1.25rem; font-weight: 700; color: var(--egypt-green);">{{ $value }}</div>
                            </div>
                        @endforeach
                    </div>

                    @if ($reportPath)
                        <div>
                            <x-filament::button
                                type="button"
                                color="success"
                                icon="heroicon-o-arrow-down-tray"
                                wire:click="downloadReport"
                            >
                                تحميل تقرير الأخطاء والتنبيهات
                            </x-filament::button>
                        </div>
                    @endif

                    @if ($lastErrors !== [])
                        <div>
                            <h3 style="margin-bottom: .5rem; font-weight: 700; color: #991b1b;">
                                أخطاء ({{ count($lastErrors) }}) — الصفوف دي ماتنفذتش
                            </h3>

                            <div style="overflow-x: auto; border: 1px solid #fecaca; border-radius: var(--r);">
                                <table style="width: 100%; border-collapse: collapse; font-size: .875rem;">
                                    <thead style="background: #fee2e2; color: #7f1d1d;">
                                        <tr>
                                            <th style="padding: .5rem; text-align: start; width: 90px;">الصف</th>
                                            <th style="padding: .5rem; text-align: start; width: 180px;">الكود</th>
                                            <th style="padding: .5rem; text-align: start;">السبب</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach (array_slice($lastErrors, 0, 50) as $error)
                                            <tr style="border-top: 1px solid #fee2e2;">
                                                <td style="padding: .5rem;">{{ $error['row'] }}</td>
                                                <td style="padding: .5rem;">{{ $error['code'] }}</td>
                                                <td style="padding: .5rem;">{{ $error['message'] }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            @if (count($lastErrors) > 50)
                                <p style="margin-top: .5rem; font-size: .8125rem; color: #858A84;">
                                    معروض أول 50 خطأ. باقي {{ count($lastErrors) - 50 }} خطأ موجود في ملف التقرير.
                                </p>
                            @endif
                        </div>
                    @endif

                    @if ($lastWarnings !== [])
                        <div>
                            <h3 style="margin-bottom: .5rem; font-weight: 700; color: #92400e;">
                                تنبيهات ({{ count($lastWarnings) }}) — الصفوف دي اتنفذت أو اتجاهلت
                            </h3>

                            <div style="overflow-x: auto; border: 1px solid #fde68a; border-radius: var(--r);">
                                <table style="width: 100%; border-collapse: collapse; font-size: .875rem;">
                                    <thead style="background: #fef3c7; color: #78350f;">
                                        <tr>
                                            <th style="padding: .5rem; text-align: start; width: 90px;">الصف</th>
                                            <th style="padding: .5rem; text-align: start; width: 180px;">الكود</th>
                                            <th style="padding: .5rem; text-align: start;">التنبيه</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach (array_slice($lastWarnings, 0, 50) as $warning)
                                            <tr style="border-top: 1px solid #fde68a;">
                                                <td style="padding: .5rem;">{{ $warning['row'] }}</td>
                                                <td style="padding: .5rem;">{{ $warning['code'] }}</td>
                                                <td style="padding: .5rem;">{{ $warning['message'] }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            @if (count($lastWarnings) > 50)
                                <p style="margin-top: .5rem; font-size: .8125rem; color: #858A84;">
                                    معروض أول 50 تنبيه. باقي {{ count($lastWarnings) - 50 }} تنبيه موجود في ملف التقرير.
                                </p>
                            @endif
                        </div>
                    @endif
                </div>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
