# Esquema sintético PostgreSQL con nombres y relaciones de las migraciones Laravel.
# No sustituye al esquema completo de la aplicación, probado por PHPUnit.
@'
CREATE TABLE users (id integer PRIMARY KEY, name text NOT NULL, role text NOT NULL, password text NOT NULL, is_approved boolean NOT NULL);
INSERT INTO users VALUES (1,'Administrador sintético','admin','synthetic-hash-admin',true),(2,'Profesional sintético','profesional','synthetic-hash-pro',true),(3,'Familiar sintético','familiar','synthetic-hash-family',true);
CREATE TABLE older_adults (id integer PRIMARY KEY, full_name text NOT NULL, created_by integer REFERENCES users, professional_caregiver_id integer REFERENCES users, family_caregiver_id integer REFERENCES users);
INSERT INTO older_adults VALUES (1,'Adulto sintético A',1,2,3),(2,'Adulto sintético B',NULL,NULL,NULL);
CREATE TABLE medications (id integer PRIMARY KEY, name text NOT NULL, quantity integer NOT NULL);
INSERT INTO medications VALUES (1,'Medicamento sintético A',30),(2,'Medicamento sintético B',20);
CREATE TABLE older_adult_medications (id integer PRIMARY KEY, older_adult_id integer REFERENCES older_adults, medication_id integer NOT NULL REFERENCES medications, dosage text NOT NULL, days jsonb NOT NULL);
INSERT INTO older_adult_medications VALUES (1,1,1,'1 tableta','["lunes","miércoles"]'),(2,NULL,2,'Inventario sin asignar','[]');
CREATE TABLE rutinas (id integer PRIMARY KEY, older_adult_id integer NOT NULL REFERENCES older_adults, created_by integer REFERENCES users, nombre text NOT NULL, actividades jsonb NOT NULL, completada boolean NOT NULL);
INSERT INTO rutinas VALUES (1,1,2,'Rutina sintética','["Movilidad","Hidratación"]',false);
CREATE TABLE incidents (id integer PRIMARY KEY, older_adult_id integer REFERENCES older_adults, reported_by integer REFERENCES users, description text NOT NULL);
INSERT INTO incidents VALUES (1,1,2,'Incidente sintético'),(2,NULL,NULL,'Incidente histórico sin asignación');
CREATE TABLE routine_notes (id integer PRIMARY KEY, older_adult_id integer NOT NULL REFERENCES older_adults, professional_caregiver_id integer NOT NULL REFERENCES users, content text NOT NULL);
INSERT INTO routine_notes VALUES (1,1,2,'Nota sintética');
CREATE TABLE medication_administrations (id integer PRIMARY KEY, older_adult_id integer NOT NULL REFERENCES older_adults, older_adult_medication_id integer REFERENCES older_adult_medications, medication_id integer NOT NULL REFERENCES medications, recorded_by integer REFERENCES users);
INSERT INTO medication_administrations VALUES (1,1,1,1,2);
CREATE TABLE medication_acquisitions (id integer PRIMARY KEY, medication_id integer NOT NULL REFERENCES medications, older_adult_id integer REFERENCES older_adults, quantity integer NOT NULL);
INSERT INTO medication_acquisitions VALUES (1,1,1,30),(2,2,NULL,20);
'@
