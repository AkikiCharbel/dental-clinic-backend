-- Initialize PostgreSQL databases for Dental Clinic
-- This script runs when the PostgreSQL container is first created

-- Create the test database for running tests
CREATE DATABASE dental_clinic_test;

-- Grant privileges to the postgres user
GRANT ALL PRIVILEGES ON DATABASE dental_clinic TO postgres;
GRANT ALL PRIVILEGES ON DATABASE dental_clinic_test TO postgres;

-- Enable useful PostgreSQL extensions
\c dental_clinic;
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pg_trgm";

\c dental_clinic_test;
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pg_trgm";
