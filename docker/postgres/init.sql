-- Initialize PostgreSQL databases for Dental Clinic
-- This script runs when the PostgreSQL container is first created

-- Create the test database for running tests
CREATE DATABASE dental_clinic_test;

-- Grant privileges to the postgres user
GRANT ALL PRIVILEGES ON DATABASE dental_clinic TO postgres;
GRANT ALL PRIVILEGES ON DATABASE dental_clinic_test TO postgres;

-- Enable useful PostgreSQL extensions
\c dental_clinic;
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";      -- UUID generation
CREATE EXTENSION IF NOT EXISTS "pg_trgm";        -- Full-text search with trigrams
CREATE EXTENSION IF NOT EXISTS "btree_gin";      -- Composite indexes for better query performance

\c dental_clinic_test;
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pg_trgm";
CREATE EXTENSION IF NOT EXISTS "btree_gin";
