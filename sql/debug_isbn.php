<?php
require_once __DIR__ . '/../config/database.php';
$conn = getDbConnection();

// Count placeholders in SQL
$sql = "INSERT INTO tasks (assignment_id, title, description, position, max_attempts, iterations_count, show_solution, show_solution_code, min_keywords_required, problem_type, code_template, hint1, hint2, hint3, stoff, expected_output, test_cases, solution_code, task_type, task_text, question_text, image_url, correct_answer, variable_overrides, randomizer_code) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$placeholders = substr_count($sql, '?');
echo "Placeholders in SQL: $placeholders\n";

// Type string
$types = "issiiiiiissssssssssssssss";
echo "Type string length: " . strlen($types) . "\n";
echo "Type string: $types\n\n";

// Variables array
$vars = [
    21,                                          // assignment_id
    "ISBN Test",                                  // title
    "Description",                                // description
    1,                                            // position
    3,                                            // max_attempts
    1,                                            // iterations_count
    1,                                            // show_solution
    1,                                            // show_solution_code
    0,                                            // min_keywords_required
    "code_completion",                            // problem_type
    "print('ISBN ')",                             // code_template
    "Hint 1",                                      // hint1
    "Hint 2",                                      // hint2
    "Hint 3",                                      // hint3
    "Stoff",                                       // stoff
    "",                                            // expected_output
    "[]",                                          // test_cases
    "print('ISBN 978-3-16-148410-0')",            // solution_code
    "code",                                        // task_type
    "Task text",                                   // task_text
    "",                                            // question_text
    null,                                          // image_url
    null,                                          // correct_answer
    null,                                          // variable_overrides
    null                                           // randomizer_code
];

echo "Number of variables: " . count($vars) . "\n\n";

if ($placeholders === strlen($types) && $placeholders === count($vars)) {
    echo "✅ All counts match!\n";
    echo "Attempting to insert...\n\n";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$vars);
    
    if ($stmt->execute()) {
        echo "✅ Successfully inserted task ID: " . $conn->insert_id . "\n";
    } else {
        echo "❌ Execute error: " . $stmt->error . "\n";
    }
} else {
    echo "❌ Counts don't match!\n";
    echo "   Placeholders: $placeholders\n";
    echo "   Type string: " . strlen($types) . "\n";
    echo "   Variables: " . count($vars) . "\n";
}
