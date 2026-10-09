<?php

namespace Espo\Modules\Jewellery\Tools;

/**
 * Decimal-string money arithmetic (bcmath). Never floats.
 *
 * Line maths:  gross = qty × unitPrice
 *              discount = gross × discount% / 100
 *              tax = (gross − discount) × tax% / 100
 *              lineTotal = gross − discount + tax
 * Each component is rounded half-up to 2 dp, then document totals are sums
 * of the rounded components, so the PDF always adds up.
 */
class Money
{
    public static function round(string $value, int $scale = 2): string
    {
        $shift = '0.' . str_repeat('0', $scale) . '5';
        $value = str_starts_with($value, '-') ? bcsub($value, $shift, $scale + 2) : bcadd($value, $shift, $scale + 2);

        return bcadd($value, '0', $scale);
    }

    public static function normalize(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '0';
        }

        $value = is_float($value) ? sprintf('%.6F', $value) : trim((string) $value);

        if (!is_numeric($value)) {
            throw new \InvalidArgumentException("Not a number: $value");
        }

        return $value;
    }

    /**
     * @return array{gross: string, discount: string, tax: string, total: string}
     */
    public static function line(mixed $quantity, mixed $unitPrice, mixed $discountPercent, mixed $taxPercent): array
    {
        $gross = self::round(bcmul(self::normalize($quantity), self::normalize($unitPrice), 8));
        $discount = self::round(bcdiv(bcmul($gross, self::normalize($discountPercent), 8), '100', 8));
        $net = bcsub($gross, $discount, 2);
        $tax = self::round(bcdiv(bcmul($net, self::normalize($taxPercent), 8), '100', 8));

        return [
            'gross' => $gross,
            'discount' => $discount,
            'tax' => $tax,
            'total' => bcadd($net, $tax, 2),
        ];
    }
}
