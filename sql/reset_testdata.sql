-- Reset und Testdaten für Python IDE
-- Created: 2026-02-08
-- Purpose: Löscht alle existierenden Daten und fügt Testbenutzer ein

USE pythonide;

-- Disable foreign key checks temporarily
SET FOREIGN_KEY_CHECKS = 0;

-- Lösche alle existierenden Daten
TRUNCATE TABLE user_assignments;
TRUNCATE TABLE test_cases;
TRUNCATE TABLE assignments;
TRUNCATE TABLE projects;
TRUNCATE TABLE users;

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- Insert test users with realistic data
-- Note: All passwords are hashed with bcrypt

-- Admin user (email: admin@pythonide.local, password: admin123)
INSERT INTO users (email, first_name, last_name, password_hash, role) VALUES
('admin@pythonide.local', 'Sarah', 'Schmidt', '$2y$10$0BDRET8OScPxeaK7xPvP1.dp7tcvVWWCaLLfWh7UIP.WyWauGx4L6', 'admin');

-- Regular test users (all passwords: test123)
INSERT INTO users (email, first_name, last_name, password_hash, role) VALUES
('max.mueller@example.com', 'Max', 'Müller', '$2y$10$h8jBmVqm9e2E3DdLYgohi.J8eNwPl95XTST0urazUo6S4dxlKKS.6', 'user'),
('anna.schulz@example.com', 'Anna', 'Schulz', '$2y$10$h8jBmVqm9e2E3DdLYgohi.J8eNwPl95XTST0urazUo6S4dxlKKS.6', 'user'),
('tom.weber@example.com', 'Tom', 'Weber', '$2y$10$h8jBmVqm9e2E3DdLYgohi.J8eNwPl95XTST0urazUo6S4dxlKKS.6', 'user'),
('lisa.fischer@example.com', 'Lisa', 'Fischer', '$2y$10$h8jBmVqm9e2E3DdLYgohi.J8eNwPl95XTST0urazUo6S4dxlKKS.6', 'user');

-- Insert sample projects for test users
INSERT INTO projects (user_id, name, description, code) VALUES
(2, 'Hallo Welt', 'Mein erstes Python-Programm', 'print("Hallo Welt!")\nprint("Willkommen bei Python IDE")'),
(2, 'Fibonacci Folge', 'Berechnet Fibonacci-Zahlen', 'def fibonacci(n):\n    if n <= 1:\n        return n\n    return fibonacci(n-1) + fibonacci(n-2)\n\nfor i in range(10):\n    print(f"F({i}) = {fibonacci(i)}")'),
(3, 'Liste sortieren', 'Sortiert eine Liste von Zahlen', 'numbers = [64, 34, 25, 12, 22, 11, 90]\nprint("Unsortiert:", numbers)\nnumbers.sort()\nprint("Sortiert:", numbers)'),
(3, 'Primzahlen', 'Findet alle Primzahlen bis 100', 'def ist_primzahl(n):\n    if n < 2:\n        return False\n    for i in range(2, int(n**0.5) + 1):\n        if n % i == 0:\n            return False\n    return True\n\nprimzahlen = [n for n in range(2, 101) if ist_primzahl(n)]\nprint("Primzahlen bis 100:", primzahlen)'),
(4, 'Temperatur Umrechner', 'Celsius zu Fahrenheit', 'def celsius_zu_fahrenheit(celsius):\n    return (celsius * 9/5) + 32\n\ncelsius = 25\nfahrenheit = celsius_zu_fahrenheit(celsius)\nprint(f"{celsius}°C = {fahrenheit}°F")');

SELECT 'Testdaten erfolgreich importiert!' as status;
SELECT COUNT(*) as 'Anzahl Benutzer' FROM users;
SELECT COUNT(*) as 'Anzahl Projekte' FROM projects;
