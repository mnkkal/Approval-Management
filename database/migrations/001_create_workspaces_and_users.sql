-- ==============================================================================
-- Schema Migration 001: Core Workspaces, Users & RBAC
-- ==============================================================================

CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pgcrypto";

-- 1. Agency Workspaces
CREATE TABLE workspaces (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(63) NOT NULL UNIQUE,
    custom_domain VARCHAR(255) UNIQUE,
    branding_settings JSONB NOT NULL DEFAULT '{
        "primary_color": "#2563EB",
        "logo_url": null,
        "company_name": "Agency",
        "email_footer": "Powered by Curve Metrics Approval Management"
    }',
    storage_quota_bytes BIGINT NOT NULL DEFAULT 10737418240, -- 10 GB
    storage_used_bytes BIGINT NOT NULL DEFAULT 0,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_workspaces_slug ON workspaces(slug);
CREATE INDEX idx_workspaces_domain ON workspaces(custom_domain) WHERE custom_domain IS NOT NULL;

-- 2. Internal Users (Agency Staff & Registered Clients)
CREATE TABLE users (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    workspace_id UUID NOT NULL REFERENCES workspaces(id) ON DELETE CASCADE,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(32) NOT NULL DEFAULT 'creator', -- owner, admin, project_manager, creator, client_reviewer, client_observer
    totp_secret VARCHAR(255), -- For authenticator-app TOTP (RFC 6238)
    totp_enabled_at TIMESTAMP WITH TIME ZONE,
    remember_token VARCHAR(100),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_workspace_user_email UNIQUE (workspace_id, email)
);

CREATE INDEX idx_users_workspace_role ON users(workspace_id, role);

-- 3. Client Organizations (CRM directory for the agency)
CREATE TABLE client_organizations (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    workspace_id UUID NOT NULL REFERENCES workspaces(id) ON DELETE CASCADE,
    name VARCHAR(255) NOT NULL,
    primary_contact_email VARCHAR(255),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_client_orgs_workspace ON client_organizations(workspace_id);
