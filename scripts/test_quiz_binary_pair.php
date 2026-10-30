<?php
declare(strict_types=1);

require_once __DIR__ . '/../components/quiz_binary_pair.php';

$expected = '11110000;00000101';
$cases = [
    [$expected, true],
    [' 1111 0000 ; 0000 0101 ', true],
    ["1111\t0000;\n00000101", true],
    ["1111\u{00a0}0000 ; 00000101", true],
    ['11110000;101', false],
    ['11110000;00000100', false],
    ['00000101;11110000', false],
    ['11110000,00000101', false],
    ['0b11110000;00000101', false],
    ['11110000;00000101;00000000', false],
    ['', false],
];
foreach ($cases as [$answer, $correct]) {
    if (compareBinaryPairAnswer($answer, $expected) !== $correct) {
        throw new RuntimeException('Unexpected comparison: ' . json_encode($answer));
    }
}
foreach ([null, '', '[]', '[{"inputs":{}}]', '[{"answer_format":true}]'] as $overrides) {
    if (usesBinaryPairAnswer($overrides)) {
        throw new RuntimeException('Legacy task opted in unexpectedly');
    }
}
if (!usesBinaryPairAnswer('[{"answer_format":"binary_pair_8"}]')) {
    throw new RuntimeException('Explicit opt-in not detected');
}
echo "PASS: whitespace tolerance, strict 8-bit pairs, wrong answers and legacy opt-out\n";

$expectedSteps = '11011100;01110110;10001010;01100110;102';
$stepCases = [
    [$expectedSteps, true],
    [' 1101 1100 ; 0111 0110 ; 1000 1010 ; 0110 0110 ; 102 ', true],
    ["11011100;\t01110110;10001010;\n01100110;102", true],
    ['11011100;1110110;10001010;01100110;102', false],
    ['11011100;01110110;10001010;1100110;102', false],
    ['11011100;01110110;10001010;01100110;103', false],
    ['11011100;01110110;10001011;01100110;102', false],
    ['01110110;11011100;10001010;01100110;102', false],
    ['11011100,01110110;10001010;01100110;102', false],
    ['11011100;01110110;10001010;01100110', false],
    [$expectedSteps . ';0', false],
    ['0b11011100;01110110;10001010;01100110;102', false],
    ['11011100;01110110;10001010;01100110;-102', false],
    ['', false],
];
foreach ($stepCases as [$answer, $correct]) {
    if (compareBinarySubtractionStepsAnswer($answer, $expectedSteps) !== $correct) {
        throw new RuntimeException('Unexpected steps comparison: ' . json_encode($answer));
    }
}
foreach ([null, '', '[]', '[{"inputs":{}}]', '[{"answer_format":"binary_pair_8"}]'] as $overrides) {
    if (usesBinarySubtractionStepsAnswer($overrides)) {
        throw new RuntimeException('Existing task opted into steps unexpectedly');
    }
}
$stepOverrides = '[{"answer_format":"binary_subtraction_steps_8"}]';
if (!usesBinarySubtractionStepsAnswer($stepOverrides) || usesBinaryPairAnswer($stepOverrides)) {
    throw new RuntimeException('Answer formats are not isolated');
}
echo "PASS: five-field subtraction answers, mandatory leading zeros, decimal result and format isolation\n";
