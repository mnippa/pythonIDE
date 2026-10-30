<?php
declare(strict_types=1);

function usesBinaryPairAnswer(?string $overrides): bool {
    if ($overrides === null || trim($overrides) === '') {
        return false;
    }
    $entries = json_decode($overrides, true, 512, JSON_THROW_ON_ERROR);
    return ($entries[0]['answer_format'] ?? null) === 'binary_pair_8';
}

function compareBinaryPairAnswer(string $answer, $expected): bool {
    if (!is_string($expected)) {
        return false;
    }
    $answer = preg_replace('/\s+/u', '', $answer);
    $expected = preg_replace('/\s+/u', '', $expected);
    return $answer !== null && $expected !== null
        && preg_match('/\A[01]{8};[01]{8}\z/', $answer) === 1
        && preg_match('/\A[01]{8};[01]{8}\z/', $expected) === 1
        && $answer === $expected;
}

function usesBinarySubtractionStepsAnswer(?string $overrides): bool {
    if ($overrides === null || trim($overrides) === '') {
        return false;
    }
    $entries = json_decode($overrides, true, 512, JSON_THROW_ON_ERROR);
    return ($entries[0]['answer_format'] ?? null) === 'binary_subtraction_steps_8';
}

function compareBinarySubtractionStepsAnswer(string $answer, $expected): bool {
    if (!is_string($expected)) {
        return false;
    }
    $answer = preg_replace('/\s+/u', '', $answer);
    $expected = preg_replace('/\s+/u', '', $expected);
    $pattern = '/\A(?:[01]{8};){4}[0-9]+\z/';
    return $answer !== null && $expected !== null
        && preg_match($pattern, $answer) === 1
        && preg_match($pattern, $expected) === 1
        && $answer === $expected;
}
