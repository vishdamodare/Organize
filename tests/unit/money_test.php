<?php
// Runnable check for the quotation maths (bcmath, half-up). php tests/unit/money_test.php
namespace Espo\Modules\Jewellery\Tools;

require __DIR__ . '/../../src/custom/Espo/Modules/Jewellery/Tools/Money.php';

$cases = [
    // [qty, price, disc%, tax%] => [gross, discount, tax, total]
    [['2', '10000.50', '5', '3'], ['20001.00', '1000.05', '570.03', '19570.98']],
    [['1.5', '3333.33', '0', '3'], ['5000.00', '0.00', '150.00', '5150.00']],   // 4999.995 rounds up
    [['1', '0.005', '0', '0'], ['0.01', '0.00', '0.00', '0.01']],
    [['3', '99.99', '100', '18'], ['299.97', '299.97', '0.00', '0.00']],
    [[1, 1000.10, null, null], ['1000.10', '0.00', '0.00', '1000.10']],         // floats/nulls tolerated
];

foreach ($cases as [$in, $expected]) {
    $got = array_values(Money::line(...$in));
    assert($got === $expected, 'line(' . json_encode($in) . ') = ' . json_encode($got) . ' expected ' . json_encode($expected));
    if ($got !== $expected) { fwrite(STDERR, "FAIL\n"); exit(1); }
}

foreach ([['2.345', '2.35'], ['-2.345', '-2.35'], ['2.344', '2.34'], ['0', '0.00']] as [$v, $r]) {
    if (Money::round($v) !== $r) { fwrite(STDERR, "FAIL round($v) = " . Money::round($v) . " expected $r\n"); exit(1); }
}

try { Money::normalize('12abc'); fwrite(STDERR, "FAIL normalize accepted junk\n"); exit(1); } catch (\InvalidArgumentException) {}

echo "money_test: all " . (count($cases) + 5) . " checks passed\n";
