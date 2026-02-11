-- Restore assignments from backup
USE pythonide;

-- Check if assignments_old exists and has data
SELECT 
    'assignments_old' as table_name,
    COUNT(*) as record_count
FROM assignments_old

UNION ALL

SELECT 
    'assignments' as table_name,
    COUNT(*) as record_count
FROM assignments;
