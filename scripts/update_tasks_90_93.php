<?php
require_once __DIR__ . '/../config/database.php';

$db = getDbConnection();

$updates = [
    90 => [
        'question_text' => 'Schreibe eine Funktion decimal_to_binary(num), die ohne bin() arbeitet.',
        'code_template' => "def decimal_to_binary(num):\n    # Schreibe deinen Code hier\n    pass\n\nresult = decimal_to_binary(13)\n",
        'solution_code' => "def decimal_to_binary(num):\n    if num == 0:\n        return \"0\"\n    binary = \"\"\n    while num > 0:\n        binary = str(num % 2) + binary\n        num //= 2\n    return binary\n\nresult = decimal_to_binary(13)\n",
        'correct_answer' => 'decimal_to_binary',
        'test_cases' => json_encode([
            [
                'type' => 'function',
                'function_name' => 'decimal_to_binary',
                'test_cases' => [
                    ['args' => [0], 'expected' => '0'],
                    ['args' => [1], 'expected' => '1'],
                    ['args' => [6], 'expected' => '110'],
                    ['args' => [13], 'expected' => '1101']
                ]
            ]
        ]),
        'validation_mode' => 'strict'
    ],
    91 => [
        'question_text' => 'Schreibe binary_to_decimal(binary_str), die einen Binärstring konvertiert.',
        'code_template' => "def binary_to_decimal(binary_str):\n    # Schreibe deinen Code hier\n    pass\n\nresult = binary_to_decimal(\"1010\")\n",
        'solution_code' => "def binary_to_decimal(binary_str):\n    decimal = 0\n    for i, bit in enumerate(reversed(binary_str)):\n        if bit == \"1\":\n            decimal += 2 ** i\n    return decimal\n\nresult = binary_to_decimal(\"1010\")\n",
        'correct_answer' => 'binary_to_decimal',
        'test_cases' => json_encode([
            [
                'type' => 'function',
                'function_name' => 'binary_to_decimal',
                'test_cases' => [
                    ['args' => ['0'], 'expected' => 0],
                    ['args' => ['1'], 'expected' => 1],
                    ['args' => ['110'], 'expected' => 6],
                    ['args' => ['1010'], 'expected' => 10],
                    ['args' => ['1111'], 'expected' => 15]
                ]
            ]
        ]),
        'validation_mode' => 'strict'
    ],
    92 => [
        'question_text' => 'Bestimme den Wert von result nach dem Code.',
        'code_template' => "result = \"\"\nfor i in range(1, 3):\n    if result:\n        result += \", \"\n    result += bin(i)\n",
        'solution_code' => "result = \"\"\nfor i in range(1, 3):\n    if result:\n        result += \", \"\n    result += bin(i)\n# result = \"0b1, 0b10\"\n",
        'correct_answer' => 'result',
        'test_cases' => null,
        'validation_mode' => 'loose'
    ],
    93 => [
        'question_text' => 'Bestimme den Wert von result nach dem Code.',
        'code_template' => "result = 0\nfor bit in \"1010\":\n    result += int(bit)\n",
        'solution_code' => "result = 0\nfor bit in \"1010\":\n    result += int(bit)\n# result = 2\n",
        'correct_answer' => 'result',
        'test_cases' => null,
        'validation_mode' => 'loose'
    ]
];

$stmt = $db->prepare(
    'UPDATE tasks SET question_text = ?, code_template = ?, solution_code = ?, correct_answer = ?, test_cases = ?, validation_mode = ? WHERE id = ?'
);

foreach ($updates as $taskId => $data) {
    $stmt->bind_param(
        'ssssssi',
        $data['question_text'],
        $data['code_template'],
        $data['solution_code'],
        $data['correct_answer'],
        $data['test_cases'],
        $data['validation_mode'],
        $taskId
    );

    if ($stmt->execute()) {
        echo "Updated Task $taskId\n";
    } else {
        echo "Failed Task $taskId: " . $stmt->error . "\n";
    }
}

$stmt->close();
$db->close();
