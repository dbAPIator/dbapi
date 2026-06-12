-- dbAPI data-plane test database (MySQL 8+ / MariaDB 10.5+)
--
-- Purpose: exercise the JSON:API data plane end-to-end — CRUD, relationships,
-- filters, sorting, pagination, views, constraints, and negative cases.
--
-- Usage (from repository root):
--   mysql -u root -p < src/tests/dbapi_dataplane.sql
--   — or —
--   composer test:dataplane-setup
--
-- Connection (tests):
--   database: dbapi_dataplane
--   host: 127.0.0.1  port: 3306  user/password: your choice
--
-- Schema body is shared with Docker init: src/tests/dataplane-schema-body.sql

DROP DATABASE IF EXISTS `dbapi_dataplane`;
CREATE DATABASE `dbapi_dataplane`
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE `dbapi_dataplane`;

SOURCE src/tests/dataplane-schema-body.sql;
