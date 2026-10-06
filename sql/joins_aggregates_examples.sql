-- Beispiele für LEFT JOIN, RIGHT JOIN und Aggregatfunktionen
-- Diese Beispiele sind als Lernhilfe für SQL-Aufgaben gedacht.

-- 1) LEFT JOIN: alle Kunden inklusive ihrer Bestellungen
SELECT
    c.customer_id,
    c.customer_name,
    o.order_id,
    o.order_total
FROM customers c
LEFT JOIN orders o ON o.customer_id = c.customer_id
ORDER BY c.customer_id, o.order_id;

-- 2) RIGHT JOIN: alle Bestellungen inklusive Kundeninformationen
SELECT
    c.customer_id,
    c.customer_name,
    o.order_id,
    o.order_total
FROM customers c
RIGHT JOIN orders o ON o.customer_id = c.customer_id
ORDER BY o.order_id;

-- 3) Aggregatfunktionen mit LEFT JOIN
SELECT
    c.customer_id,
    c.customer_name,
    COUNT(o.order_id) AS anzahl_bestellungen,
    SUM(o.order_total) AS gesamtumsatz,
    AVG(o.order_total) AS durchschnittlicher_bestellwert
FROM customers c
LEFT JOIN orders o ON o.customer_id = c.customer_id
GROUP BY c.customer_id, c.customer_name
ORDER BY c.customer_id;

-- 4) Aggregatfunktionen nur für Bestellungen mit Mindestwert
SELECT
    c.customer_id,
    c.customer_name,
    COUNT(o.order_id) AS anzahl_bestellungen,
    SUM(o.order_total) AS gesamtumsatz,
    AVG(o.order_total) AS durchschnittlicher_bestellwert
FROM customers c
LEFT JOIN orders o ON o.customer_id = c.customer_id
GROUP BY c.customer_id, c.customer_name
HAVING SUM(o.order_total) > 100
ORDER BY gesamtumsatz DESC;

-- 5) Alternative mit COUNT(*) und COUNT(spalte)
SELECT
    COUNT(*) AS gesamtzeilen,
    COUNT(o.order_id) AS bestellungen_mit_order_id
FROM customers c
LEFT JOIN orders o ON o.customer_id = c.customer_id;
