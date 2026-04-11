<?php

namespace App\Services;

/**
 * PennyRoutingService
 *
 * خوارزمية توزيع الكسور العشرية (Penny Routing) بشكل حتمي.
 *
 * المشكلة: عند تقسيم مبلغ مثل 100.00 على 3 أعضاء، تكون الحصة الأساسية
 * 33.33 لكل عضو، والمجموع 99.99 — يبقى باقٍ 0.01 لا يمكن تجاهله.
 *
 * الحل: استخدام SHA-256 hash للـ reference لتحديد العضو الذي يستلم
 * الكسر الزائد بشكل حتمي (نفس النتيجة دائماً لنفس المدخلات).
 *
 * Requirements: 2.1, 2.2, 2.3
 */
class PennyRoutingService
{
    /**
     * حساب التقسيم المتساوي مع توزيع الكسور العشرية بشكل حتمي.
     *
     * الخوارزمية:
     * 1. حساب الحصة الأساسية = floor(amount * 100 / count) / 100
     * 2. حساب الباقي بالسنتات = (amount * 100) - (base * 100 * count)
     * 3. استخدام SHA-256(reference) لتحديد أول N أعضاء يستلمون سنتاً إضافياً
     *
     * @param  float    $totalAmount  المبلغ الإجمالي للمصروف
     * @param  int[]    $memberIds    قائمة معرّفات الأعضاء المشاركين
     * @param  string   $reference    المرجع الفريد للمصروف (EXP-XXXX)
     * @return array<int, float>      مصفوفة [member_id => amount]
     *
     * @throws \InvalidArgumentException إذا كانت المدخلات غير صالحة
     */
    public function calculateEqualSplits(
        float $totalAmount,
        array $memberIds,
        string $reference
    ): array {
        if ($totalAmount <= 0) {
            throw new \InvalidArgumentException('Total amount must be greater than zero.');
        }

        $count = count($memberIds);

        if ($count === 0) {
            throw new \InvalidArgumentException('Member list cannot be empty.');
        }

        // العمل بالسنتات (integers) لتجنب أخطاء الفاصلة العائمة
        $totalCents = (int) round($totalAmount * 100);
        $baseCents  = intdiv($totalCents, $count);       // الحصة الأساسية بالسنتات
        $remainder  = $totalCents - ($baseCents * $count); // الباقي بالسنتات

        // تحديد الأعضاء الذين يستلمون سنتاً إضافياً باستخدام SHA-256
        $recipientOffset = $this->computeRecipientOffset($reference, $count);

        $splits = [];

        foreach ($memberIds as $index => $memberId) {
            // العضو يستلم سنتاً إضافياً إذا كان ضمن أول `remainder` أعضاء
            // بدءاً من الـ offset المحسوب من الـ hash
            $adjustedIndex = ($index - $recipientOffset + $count) % $count;
            $extra         = ($adjustedIndex < $remainder) ? 1 : 0;

            $splits[$memberId] = round(($baseCents + $extra) / 100, 2);
        }

        return $splits;
    }

    /**
     * حساب الـ offset الحتمي لتوزيع الكسور باستخدام SHA-256.
     *
     * يُحوَّل الـ hash إلى عدد صحيح كبير ثم يُؤخذ باقي القسمة على عدد الأعضاء،
     * مما يضمن توزيعاً موحداً وحتمياً.
     *
     * Requirements: 2.3
     *
     * @param  string $reference  المرجع الفريد للمصروف
     * @param  int    $count      عدد الأعضاء
     * @return int                الـ offset (0 إلى count-1)
     */
    public function computeRecipientOffset(string $reference, int $count): int
    {
        if ($count <= 0) {
            return 0;
        }

        // SHA-256 يُنتج 64 حرفاً hex — نأخذ أول 16 حرفاً (64 بت) لتجنب overflow
        $hash = hash('sha256', $reference);
        $hex  = substr($hash, 0, 16);

        // تحويل hex إلى عدد صحيح كبير باستخدام bcmath إن توفّر، وإلا fmod
        if (function_exists('bcmod')) {
            $decimal = base_convert($hex, 16, 10);
            $offset  = (int) bcmod($decimal, (string) $count);
        } else {
            $decimal = hexdec($hex);
            $offset  = (int) fmod($decimal, $count);
        }

        return $offset;
    }

    /**
     * التحقق من أن مجموع التقسيمات يساوي المبلغ الإجمالي بدقة.
     *
     * Requirements: 2.1, 2.5
     *
     * @param  array<int, float> $splits       مصفوفة [member_id => amount]
     * @param  float             $totalAmount  المبلغ الإجمالي المتوقع
     * @return bool
     */
    public function verifySplitSum(array $splits, float $totalAmount): bool
    {
        $sumCents   = array_sum(array_map(fn($a) => (int) round($a * 100), $splits));
        $totalCents = (int) round($totalAmount * 100);

        return $sumCents === $totalCents;
    }
}
