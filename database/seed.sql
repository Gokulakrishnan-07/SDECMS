-- ============================================================================
-- SECMS seed data — FRESH INSTALL (no demo data).
-- Creates only what the system needs to function:
--   • the current financial year (2026-27, active)
--   • the institutional departments (system structure, not demo data)
--   • department sub-units for Transport and Civil Works
--   • ONE administrator account
--   • basic system settings
--
-- Import AFTER schema.sql:  mysql -u root secms < database/seed.sql
--
-- Administrator login:
--   Email:    admin@sdc.edu.in
--   Password: Admin@123        ⚠ change it after the first login
--             (Settings → Change Password)
--
-- Create every other user yourself from User Management after logging in.
-- ============================================================================

USE secms;

-- Financial year (Indian FY: April – March) ----------------------------------
INSERT INTO financial_years (label, year_code, start_date, end_date, is_active) VALUES
('2026-27', 2026, '2026-04-01', '2027-03-31', 1);

-- Departments (code = sanction number prefix) --------------------------------
-- workflow_type: 'full' = School/College procurement (sanction commits budget,
--   requisition draws sanction balance, PO payment books expense);
-- 'simple' = service departments (approved requisition books the expense).
-- has_units = requisitions must pick a sub-unit (Transport, Civil Works).
INSERT INTO departments (name, code, icon, workflow_type, has_units) VALUES
('College',                 'COL', 'fa-building-columns',    'full',   0),
('School',                  'SCH', 'fa-school',              'full',   0),
('Trust',                   'TRT', 'fa-handshake-angle',     'simple', 0),
('Transport & Security',    'TRN', 'fa-bus',                 'simple', 1),
('Farming',                 'FAR', 'fa-tractor',             'simple', 0),
('Koodam',                  'KOO', 'fa-people-roof',         'simple', 0),
('Guest House',             'GHS', 'fa-hotel',               'simple', 0),
('Ayurveda Clinic',         'AYU', 'fa-leaf',                'simple', 0),
('Temple',                  'TMP', 'fa-gopuram',             'simple', 0),
('Goshala',                 'GOS', 'fa-cow',                 'simple', 0),
('Padasala',                'PAD', 'fa-book-open-reader',    'simple', 0),
('Electrical & Plumbing',   'ELP', 'fa-bolt',                'simple', 0),
('Housekeeping',            'HKP', 'fa-broom',               'simple', 0),
('Gardening',               'GRD', 'fa-seedling',            'simple', 0),
('Village Welfare',         'VLW', 'fa-hand-holding-heart',  'simple', 0),
('Camp',                    'CMP', 'fa-campground',          'simple', 0),
('Civil Works',             'CVL', 'fa-helmet-safety',       'simple', 1),
('Miscellaneous',           'MSC', 'fa-layer-group',         'simple', 0);

-- Department sub-units --------------------------------------------------------
-- Transport → School / College / Trust Transport.
INSERT INTO department_units (department_id, unit_code, unit_name) VALUES
((SELECT id FROM departments WHERE code = 'TRN'), 'SCH-TRN', 'School Transport'),
((SELECT id FROM departments WHERE code = 'TRN'), 'COL-TRN', 'College Transport'),
((SELECT id FROM departments WHERE code = 'TRN'), 'TRT-TRN', 'Trust Transport');

-- Civil Works → Study Centre / Auditorium / Learning Centre / Conference Hall
-- (these were previously separate departments, now units under Civil Works).
INSERT INTO department_units (department_id, unit_code, unit_name) VALUES
((SELECT id FROM departments WHERE code = 'CVL'), 'STC-CVL', 'Study Centre'),
((SELECT id FROM departments WHERE code = 'CVL'), 'AUD-CVL', 'Auditorium'),
((SELECT id FROM departments WHERE code = 'CVL'), 'LRC-CVL', 'Learning Centre'),
((SELECT id FROM departments WHERE code = 'CVL'), 'CNF-CVL', 'Conference Hall');

-- The one and only administrator account --------------------------------------
-- Password: Admin@123 (bcrypt) — CHANGE AFTER FIRST LOGIN.
INSERT INTO users (name, email, password, role, department_id) VALUES
('Administrator', 'admin@sdc.edu.in', '$2y$10$0PcckUPu3XLxnpBHNDlTm.zOseSYjYwczWpibsWHO67FArVPKH6uu', 'administrator', NULL);

-- System settings ---------------------------------------------------------------
INSERT INTO system_settings (`key`, `value`) VALUES
('institution_name',    'Swami Dayanandha Educational Institutions'),
('institution_place',   'Manjakkudi, Thiruvarur District, Tamil Nadu'),
('low_budget_threshold','80'),
('currency_symbol',     '₹');
