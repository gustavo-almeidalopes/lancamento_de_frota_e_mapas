-- ============================================================
--  ECOLIMP — PostgreSQL Schema (Neon)
--  Run this file once against your Neon database to bootstrap
--  all tables and seed them with the prototype mock data.
--
--  Connection example:
--    psql "$DATABASE_URL" -f database/schema.sql
-- ============================================================

-- ── Extensions ────────────────────────────────────────────
-- pgcrypto provides gen_random_uuid(); available on Neon by default.
CREATE EXTENSION IF NOT EXISTS pgcrypto;

-- ── Drop order matters (FK-safe) ──────────────────────────
DROP TABLE IF EXISTS dispatch_orders  CASCADE;
DROP TABLE IF EXISTS equipment_status CASCADE;
DROP TABLE IF EXISTS operators        CASCADE;
DROP TABLE IF EXISTS drivers          CASCADE;

-- ============================================================
--  TABLE: drivers
--  Stores truck drivers (motoristas) with CNH information.
-- ============================================================
CREATE TABLE drivers (
    id         SERIAL       PRIMARY KEY,
    matricula  VARCHAR(20)  NOT NULL UNIQUE,
    name       VARCHAR(120) NOT NULL,
    cnh        VARCHAR(11)  NOT NULL UNIQUE,
    categoria  VARCHAR(2)   NOT NULL DEFAULT 'B'
                            CHECK (categoria IN ('A','B','C','D','E')),
    turno      VARCHAR(20)  NOT NULL DEFAULT '1º TURNO',
    setor      VARCHAR(30)  NOT NULL DEFAULT 'OPERAÇÃO',
    status     VARCHAR(10)  NOT NULL DEFAULT 'ATIVO'
                            CHECK (status IN ('ATIVO','INATIVO')),
    created_at TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);

-- ── Seed: prototype mock data ─────────────────────────────
INSERT INTO drivers (matricula, name, cnh, categoria, turno, setor, status) VALUES
    ('MOT-1001', 'JOÃO DA SILVA',   '12345678900', 'D', '1º TURNO', 'OPERAÇÃO',      'ATIVO'),
    ('MOT-1002', 'CARLOS PEREIRA',  '09876543211', 'E', '2º TURNO', 'OPERAÇÃO',      'ATIVO'),
    ('MOT-1003', 'ROBERTO ALVES',   '11223344556', 'B', '3º TURNO', 'ADMINISTRATIVO','INATIVO');

-- ============================================================
--  TABLE: operators
--  Stores field operators / coletores.
-- ============================================================
CREATE TABLE operators (
    id         SERIAL       PRIMARY KEY,
    matricula  VARCHAR(20)  NOT NULL UNIQUE,
    name       VARCHAR(120) NOT NULL,
    turno      VARCHAR(20)  NOT NULL DEFAULT '1º TURNO',
    status     VARCHAR(10)  NOT NULL DEFAULT 'ATIVO'
                            CHECK (status IN ('ATIVO','INATIVO','FÉRIAS','AFASTADO')),
    created_at TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);

-- ── Seed ─────────────────────────────────────────────────
INSERT INTO operators (matricula, name, turno, status) VALUES
    ('99101', 'MATEUS COSTA',   '1º TURNO', 'ATIVO'),
    ('99102', 'LUCAS MARTINS',  '1º TURNO', 'ATIVO'),
    ('99201', 'FERNANDO SOUZA', '3º TURNO', 'FÉRIAS');

-- ============================================================
--  TABLE: equipment_status
--  Battery / tracker status for each piece of fleet equipment.
-- ============================================================
CREATE TABLE equipment_status (
    id                 SERIAL        PRIMARY KEY,
    equipment_id       VARCHAR(60)   NOT NULL,        -- human label e.g. "CARRINHO 01"
    plate              VARCHAR(10)   NULL,             -- truck plate if attached
    battery_status     VARCHAR(30)   NOT NULL DEFAULT 'DESCARREGADO'
                                     CHECK (battery_status IN (
                                         'DESCARREGADO','RECARGA URGENTE',
                                         'IMINÊNCIA DE RECARGA','CARREGADO','CARGA TRABALHO'
                                     )),
    communication      VARCHAR(5)    NOT NULL DEFAULT 'OFF'
                                     CHECK (communication IN ('ON','OFF')),
    battery_percentage SMALLINT      NOT NULL DEFAULT 0
                                     CHECK (battery_percentage BETWEEN 0 AND 100),
    last_communication TIMESTAMPTZ   NULL,
    created_at         TIMESTAMPTZ   NOT NULL DEFAULT NOW()
);

-- ── Seed: two sample rows matching the prototype table ────
INSERT INTO equipment_status
    (equipment_id, plate, battery_status, communication, battery_percentage, last_communication)
VALUES
    ('CARRINHO 01', NULL, 'DESCARREGADO',  'OFF', 0,  '2026-04-01 14:30:00+00'),
    ('CARRINHO 02', NULL, 'CARREGADO',     'ON',  98, '2026-04-04 07:15:00+00');

-- ============================================================
--  TABLE: dispatch_orders
--  One row per truck per dispatch event.
--
--  maps_json and operators_json store arrays as JSONB to support
--  the "multiple maps / multiple operators per truck" requirement
--  without requiring separate junction tables for the MVP.
--  Each element is a plain string (map code or operator matricula).
--
--  Example JSONB values:
--    maps_json      = '["CV100200MT001", "CV100200MT002"]'
--    operators_json = '["99101", "99102"]'
-- ============================================================
CREATE TABLE dispatch_orders (
    id             SERIAL        PRIMARY KEY,
    tracker_id     VARCHAR(30)   NOT NULL,
    tracker_status VARCHAR(15)   NOT NULL DEFAULT 'ATIVO'
                                  CHECK (tracker_status IN ('ATIVO','DESATIVADO','MANUTENÇÃO')),
    truck_plate    VARCHAR(10)   NOT NULL,
    driver_name    VARCHAR(120)  NOT NULL DEFAULT '',
    maps_json      JSONB         NOT NULL DEFAULT '[]'::jsonb,
    operators_json JSONB         NOT NULL DEFAULT '[]'::jsonb,
    created_at     TIMESTAMPTZ   NOT NULL DEFAULT NOW()
);

-- GIN indexes accelerate JSONB containment (@>) and key-exists (?) queries
CREATE INDEX idx_dispatch_maps      ON dispatch_orders USING GIN (maps_json);
CREATE INDEX idx_dispatch_operators ON dispatch_orders USING GIN (operators_json);

-- ── Seed: two sample orders matching carregarProgramacaoAnterior ─
INSERT INTO dispatch_orders
    (tracker_id, tracker_status, truck_plate, driver_name, maps_json, operators_json)
VALUES
    ('RST-1001', 'ATIVO',      'ABC1234', 'JOÃO SILVA',    '["CV100200MT001"]',        '["99101","99102"]'),
    ('RST-1002', 'MANUTENÇÃO', 'XYZ9876', 'CARLOS MENDES', '["CV100200MT003"]',        '["99201"]');
