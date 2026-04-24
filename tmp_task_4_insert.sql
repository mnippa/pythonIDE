INSERT INTO tasks (
    assignment_id, task_number, title, problem_description, problem_type,
    code_template, solution_code, randomizer_code, test_cases, difficulty, hints
) VALUES (
    29, 4, 'Verschachtelte Schleife mit Zähler',
    'Schreibe ein Programm mit drei verschachtelten Schleifen (i, j, k). Die Ranges für die drei Schleifen werden zufällig generiert (jeweils 1-5). In der innersten Schleife soll ein Counter 3 Mal inkrementiert werden pro Iteration.',
    'code_completion',
    '#INIT START\nimport random\nrange_i = random.randint(1, 5)\nrange_j = random.randint(1, 5)\nrange_k = random.randint(1, 5)\n#INIT END\n\ncounter = 0\nfor i in range(range_i):\n    for j in range(range_j):\n        for k in range(range_k):\n            # Schreibe hier den Code um den Counter zu inkrementieren\n            pass\n',
    '#INIT START\nimport random\nrange_i = random.randint(1, 5)\nrange_j = random.randint(1, 5)\nrange_k = random.randint(1, 5)\n#INIT END\n\ncounter = 0\nfor i in range(range_i):\n    for j in range(range_j):\n        for k in range(range_k):\n            counter += 1\n            counter += 1\n            counter += 1\n',
    'import random\nrange_i = random.randint(1, 5)\nrange_j = random.randint(1, 5)\nrange_k = random.randint(1, 5)\nvalues = {\n    "range_i": range_i,\n    "range_j": range_j,\n    "range_k": range_k\n}',
    '{"mode": "intelligent", "tests": [{"inputs": ["range_i", "range_j", "range_k"], "outputs": ["counter"]}]}',
    'medium',
    'Denke an die Struktur: Drei verschachtelte for-Schleifen. Der Counter wird in der innersten Schleife inkrementiert.'
);
