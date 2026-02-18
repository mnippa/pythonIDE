
// Kurz-Test: Prüfe die API für Task 79
fetch('api/tasks/list.php?assignment_id=12&test_mode=1', {
  method: 'GET',
  credentials: 'include',
  headers: {
    'Accept': 'application/json'
  }
})
  .then(r => r.json())
  .then(data => {
    const task79 = data.tasks.find(t => t.id == 79);
    console.log('=== Task 79 API Response ===');
    console.log('task_type:', task79.task_type);
    console.log('solution_code exists:', !!task79.solution_code);
    console.log('code_template exists:', !!task79.code_template);
    console.log('solution_code preview:', task79.solution_code?.substring(0, 100));
    console.log('code_template preview:', task79.code_template?.substring(0, 100));
  })
  .catch(e => console.error('API Error:', e));
