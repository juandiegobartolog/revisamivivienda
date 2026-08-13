INSERT INTO departments (code,name,is_active)
SELECT '66','Risaralda',1 WHERE NOT EXISTS (SELECT 1 FROM departments WHERE code='66');
INSERT INTO departments (code,name,is_active)
SELECT '17','Caldas',1 WHERE NOT EXISTS (SELECT 1 FROM departments WHERE code='17');
INSERT INTO departments (code,name,is_active)
SELECT '63','Quindío',1 WHERE NOT EXISTS (SELECT 1 FROM departments WHERE code='63');
INSERT INTO departments (code,name,is_active)
SELECT '11','Bogotá D.C.',1 WHERE NOT EXISTS (SELECT 1 FROM departments WHERE code='11');
INSERT INTO departments (code,name,is_active)
SELECT '76','Valle del Cauca',1 WHERE NOT EXISTS (SELECT 1 FROM departments WHERE code='76');

INSERT INTO municipalities (department_id,code,name,is_enabled_for_reports)
SELECT id,'66001','Pereira',1 FROM departments WHERE code='66' AND NOT EXISTS (SELECT 1 FROM municipalities WHERE code='66001');
INSERT INTO municipalities (department_id,code,name,is_enabled_for_reports)
SELECT id,'66170','Dosquebradas',1 FROM departments WHERE code='66' AND NOT EXISTS (SELECT 1 FROM municipalities WHERE code='66170');
INSERT INTO municipalities (department_id,code,name,is_enabled_for_reports)
SELECT id,'66682','Santa Rosa de Cabal',1 FROM departments WHERE code='66' AND NOT EXISTS (SELECT 1 FROM municipalities WHERE code='66682');
INSERT INTO municipalities (department_id,code,name,is_enabled_for_reports)
SELECT id,'17001','Manizales',1 FROM departments WHERE code='17' AND NOT EXISTS (SELECT 1 FROM municipalities WHERE code='17001');
INSERT INTO municipalities (department_id,code,name,is_enabled_for_reports)
SELECT id,'63001','Armenia',1 FROM departments WHERE code='63' AND NOT EXISTS (SELECT 1 FROM municipalities WHERE code='63001');
INSERT INTO municipalities (department_id,code,name,is_enabled_for_reports)
SELECT id,'11001','Bogotá',1 FROM departments WHERE code='11' AND NOT EXISTS (SELECT 1 FROM municipalities WHERE code='11001');
INSERT INTO municipalities (department_id,code,name,is_enabled_for_reports)
SELECT id,'76001','Cali',1 FROM departments WHERE code='76' AND NOT EXISTS (SELECT 1 FROM municipalities WHERE code='76001');
