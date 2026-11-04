<?php

namespace App\Services\Excel;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;

/** بيعمل قالب الاستيراد: نفس شكل الإكسيل الأصلي + أعمدة التليفون والنائب. */
class ArrearsTemplateWriter
{
    /** ترتيب الأعمدة (العمود الأول للترقيم وعنوانه فاضي زي الملف الأصلي) */
    public const HEADERS = [
        '', 'نوع القسط', 'م', 'كود', 'الاسم', 'المركز',
        'مبلغ الدفعة المقدمة', 'اجمالى الثمن بالفايدة', 'تاريخ المقدم',
        'القسط الاول', 'تاريخ السداد',
        'القسط الثاني', 'تاريخ السداد',
        'القسط الثالث', 'تاريخ السداد',
        'القسط الرابع', 'تاريخ السداد',
        'القسط الخامس', 'تاريخ السداد',
        'القسط السادس', 'تاريخ السداد',
        'القسط السابع', 'تاريخ السداد',
        'ملاحظات',
        'الرقم القومي', 'هاتف المواطن', 'اسم النائب', 'صلة القرابة', 'هاتف النائب',
        'الفائدة',
    ];

    private const INSTRUCTIONS = [
        'تعليمات تعبئة الملف',
        '',
        '1) كل صف = عقد واحد. «كود» لازم يكون فريد لكل عقد، ولو الكود موجود في النظام الصف بيتتخطى.',
        '2) «نوع القسط»: ثلاث سنوات / خمس سنوات / سبع سنوات / سداد 100% (كاش) / تم سداد كامل ثمن الأرض.',
        '3) «تم سداد كامل ثمن الأرض»: عقد مقسّط خلّص أقساطه. نوعه بيتحدد من عدد الأقساط المكتوبة (3 أو 5 أو 7). لو قسط من غير تاريخ سداد بيتسجل مسدد من غير تاريخ ويطلع تنبيه.',
        '4) «سداد 100%»: دفعة واحدة. «مبلغ الدفعة المقدمة» = المبلغ المدفوع كله، و«تاريخ المقدم» = تاريخ الدفع، ومن غير أقساط.',
        '5) «تاريخ سداد» جنب القسط = تاريخ الدفع الفعلي. فاضي = القسط لسه ماتدفعش (متأخر أو معاده لسه مجاش).',
        '6) تاريخ الاستحقاق بيتحسب تلقائي: تاريخ المقدم + N سنة للقسط رقم N. لو «تاريخ المقدم» فاضي بيتسجل القسط من غير موعد استحقاق مع تنبيه، وتقدر تضيف الموعد يدوي وقت السداد. القسط من غير موعد مش بيظهر في المتأخرات لحد ما يتضاف الموعد.',
        '7) «اجمالى الثمن بالفايدة»: النظام بيحسبه = المقدم + مجموع الأقساط، ولو القيمة اللي في الملف مختلفة بيطلع تنبيه.',
        '8) تاريخ مش موجود في التقويم (زي 30/02/2020) بيتحسب آخر يوم في الشهر ويطلع تنبيه، والصف بيتستورد.',
        '9) المواطن: لو «الرقم القومي» مكتوب بيتطابق بيه، وإلا بيتطابق بالاسم + المركز. نفس المواطن ممكن يظهر في أكتر من صف بأكواد مختلفة.',
        '10) أرقام الهاتف: افصل بين أكتر من رقم بعلامة / أو ،   (الرقم الغلط بيتجاهل مع تنبيه).',
        '11) النائب: لو «اسم النائب» فاضي بيتجاهل باقي بيانات النائب. لو النائب اختلف عن الحالي بيتحل محله.',
        '12) ملاحظة الصف بتتسجل على أول قسط لسه متسددش.',
        '',
        'الصف بيتتخطى (مع ذكر السبب في تقرير الأخطاء) في الحالات دي بس:',
        '- كود أو اسم أو نوع سداد ناقص/غير معروف، أو الكود مكرر.',
        '- قسط من غير مبلغ أو مبلغه غير رقمي، أو عدد الأقساط ما بيطابقش نوع السداد.',
        '- تاريخ سداد غير مفهوم أو في المستقبل.',
    ];

    public function write(string $path): void
    {
        $options = new Options();
        $options->setColumnWidth(5, 1);
        $options->setColumnWidth(26, 2);
        $options->setColumnWidth(8, 3);
        $options->setColumnWidth(12, 4);
        $options->setColumnWidth(24, 5);
        $options->setColumnWidth(14, 6);
        $options->setColumnWidth(16, 7, 8, 9);
        $options->setColumnWidth(14, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23);
        $options->setColumnWidth(26, 24);
        $options->setColumnWidth(22, 25, 26, 27, 28, 29);
        $options->setColumnWidth(12, 30);

        OpenSpoutTempFolder::configure($options, $path);

        $writer = new Writer($options);
        $writer->openToFile($path);

        $sheet = $writer->getCurrentSheet();
        $sheet->setName('البيانات');
        $sheet->setSheetView((new SheetView())->setRightToLeft(true)->setFreezeRow(2));

        $header = (new Style())->setFontBold()->setBackgroundColor(Color::rgb(221, 235, 247));
        $writer->addRow(Row::fromValues(self::HEADERS, $header));

        $info = $writer->addNewSheetAndMakeItCurrent();
        $info->setName('تعليمات');
        $info->setSheetView((new SheetView())->setRightToLeft(true));
        $info->setColumnWidth(120, 1);

        foreach (self::INSTRUCTIONS as $i => $line) {
            $writer->addRow(Row::fromValues([$line], $i === 0 ? (new Style())->setFontBold() : null));
        }

        $writer->close();
    }
}
