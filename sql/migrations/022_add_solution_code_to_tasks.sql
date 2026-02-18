-- Migration: Add solution_code to test tasks 47 and 50
-- Date: 2026-02-17
-- Purpose: Add solution code for Code-type tasks to enable solution display in test editor

-- Task 47: Vergleichsoperatoren (Comparison operators)
UPDATE tasks 
SET solution_code = 'x = 10
y = 5
# TODO: setze result auf True wenn x > y, sonst False
result = x > y
print(result)'
WHERE id = 47 AND task_type = 'code';

-- Task 50: Bereichsprüfung (Range check)
UPDATE tasks 
SET solution_code = 'age = 19
has_ticket = True
# TODO: erlauben wenn age >= 18 AND has_ticket
if age >= 18 and has_ticket:
    print("allowed")
else:
    print("denied")'
WHERE id = 50 AND task_type = 'code';
