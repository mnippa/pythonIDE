-- Migration: Add test_cases column to tasks table
-- Purpose: Store JSON test cases for code validation

ALTER TABLE tasks ADD COLUMN test_cases LONGTEXT COMMENT 'JSON array of test cases: [{input: "", expected: ""},...]' AFTER expected_output;
ALTER TABLE tasks ADD COLUMN validation_mode VARCHAR(20) DEFAULT 'loose' COMMENT 'loose (ignore spaces) or strict (exact match)' AFTER test_cases;

-- Also add to assignment_files for template support
ALTER TABLE assignment_files ADD COLUMN test_cases LONGTEXT COMMENT 'JSON test cases' AFTER content;
ALTER TABLE assignment_files ADD COLUMN validation_mode VARCHAR(20) DEFAULT 'loose' AFTER test_cases;
