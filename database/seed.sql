-- Demo data: the bakery's range, its opening hours, two people in the back office and four weeks of past orders
INSERT INTO products (slug, name, description, category, price_cents, stock, picture, position) VALUES
('sourdough-loaf', '{"en": "Sourdough loaf", "de": "Sauerteigbrot"}',
 '{"en": "Rye and wheat, 36 hours of dough rest, a dark crust that crackles.", "de": "Roggen und Weizen, 36 Stunden Teigruhe, eine dunkle Kruste, die knackt."}',
 'bread', 520, 14, 'sourdough-loaf.svg', 1),
('spelt-rolls', '{"en": "Spelt rolls (4)", "de": "Dinkelbrötchen (4)"}',
 '{"en": "Four wholegrain spelt rolls with sunflower seeds.", "de": "Vier Dinkelvollkornbrötchen mit Sonnenblumenkernen."}',
 'rolls', 340, 30, 'spelt-rolls.svg', 2),
('pretzel', '{"en": "Lye pretzel", "de": "Laugenbrezel"}',
 '{"en": "Swabian style: thin arms, a thick belly, coarse salt.", "de": "Schwäbisch: dünne Ärmchen, ein dicker Bauch, grobes Salz."}',
 'rolls', 120, 40, 'pretzel.svg', 3),
('croissant', '{"en": "Butter croissant", "de": "Buttercroissant"}',
 '{"en": "Laminated by hand with Alpine butter, baked every morning.", "de": "Von Hand mit Alpenbutter tourniert, jeden Morgen frisch gebacken."}',
 'pastry', 180, 36, 'croissant.svg', 4),
('cinnamon-roll', '{"en": "Cinnamon roll", "de": "Zimtschnecke"}',
 '{"en": "Soft yeast dough, cinnamon sugar, a thin glaze.", "de": "Weicher Hefeteig, Zimtzucker, eine dünne Glasur."}',
 'pastry', 260, 24, 'cinnamon-roll.svg', 5),
('butter-cake', '{"en": "Butter cake (slice)", "de": "Butterkuchen (Stück)"}',
 '{"en": "Yeast cake with butter, sugar and almonds, from the tray.", "de": "Hefekuchen mit Butter, Zucker und Mandeln, vom Blech."}',
 'cake', 230, 20, 'butter-cake.svg', 6),
('plum-cake', '{"en": "Plum cake (slice)", "de": "Zwetschgenkuchen (Stück)"}',
 '{"en": "Late-summer plums on shortcrust, with crumble on request.", "de": "Spätsommerzwetschgen auf Mürbeteig, auf Wunsch mit Streuseln."}',
 'cake', 290, 16, 'plum-cake.svg', 7),
('rye-bread', '{"en": "Black Forest rye", "de": "Schwarzwälder Roggenbrot"}',
 '{"en": "Ninety percent rye, caraway and coriander, keeps for a week.", "de": "Neunzig Prozent Roggen, Kümmel und Koriander, hält eine Woche."}',
 'bread', 480, 10, 'rye-bread.svg', 8);

INSERT INTO opening_hours (weekday, opens, closes) VALUES
(1, '06:30', '18:00'), (2, '06:30', '18:00'), (3, '06:30', '18:00'),
(4, '06:30', '18:00'), (5, '06:30', '18:00'), (6, '07:00', '13:00');

-- Passwords: croissant (owner) and pretzel (staff)
INSERT INTO users (email, name, password_hash, role) VALUES
('anna@sonnenkorn.test', 'Anna', '$2y$12$cqbSo.j9MwrHRHzj7IVvkOcHkFG8rOnDJDWb5IXUW.1ab7KBV0q6a', 'owner'),
('ben@sonnenkorn.test', 'Ben', '$2y$12$M7B1lxyQrPHS7nh4kOWRPup06ouLXyiX3FW.0gCWFQCPaDb67Rjam', 'staff');

-- Four weeks of picked-up orders, three to six a day, two products each
INSERT INTO orders (code, customer_name, email, pickup_date, locale, status, created_at, updated_at)
SELECT format('seed-%s-%s', d, k),
       (ARRAY['Mia', 'Noah', 'Emma', 'Paul', 'Lena', 'Felix'])[1 + (d + k) % 6],
       'guest@example.test',
       current_date - d,
       CASE WHEN k % 2 = 0 THEN 'de' ELSE 'en' END,
       'picked_up',
       (current_date - d - 1) + time '09:00' + k * interval '17 minutes',
       (current_date - d) + time '10:00'
FROM generate_series(1, 28) AS d, generate_series(1, 6) AS k
WHERE k <= 3 + d % 4;

INSERT INTO order_lines (order_id, product_id, name, price_cents, quantity)
SELECT o.id, p.id, p.name ->> o.locale, p.price_cents, line.quantity
FROM orders o
CROSS JOIN LATERAL (
    SELECT split_part(o.code, '-', 2)::int AS d, split_part(o.code, '-', 3)::int AS k
) AS seed
CROSS JOIN LATERAL (VALUES
    ((seed.d * 7 + seed.k * 3) % 8 + 1, 1 + (seed.d + seed.k) % 3),
    ((seed.d * 7 + seed.k * 3 + 1) % 8 + 1, 1 + (seed.d * seed.k) % 2)
) AS line (position, quantity)
JOIN products p ON p.position = line.position
WHERE o.code LIKE 'seed-%';
