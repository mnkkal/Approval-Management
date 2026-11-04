-- ==============================================================================
-- Schema Migration 003: Guest Review Links & Zero-Cost Email OTP Verification
-- ==============================================================================

-- 1. Guest Review Invitations (Tokenized Links for Clients)
CREATE TABLE guest_review_invitations (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    workspace_id UUID NOT NULL REFERENCES workspaces(id) ON DELETE CASCADE,
    project_id UUID NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
    deliverable_id UUID REFERENCES deliverables(id) ON DELETE CASCADE, -- NULL if invited to whole project
    recipient_email VARCHAR(255) NOT NULL,
    recipient_name VARCHAR(255),
    token_hash CHAR(64) NOT NULL UNIQUE, -- SHA-256 of raw invitation token
    can_approve BOOLEAN NOT NULL DEFAULT TRUE,
    expires_at TIMESTAMP WITH TIME ZONE NOT NULL,
    revoked_at TIMESTAMP WITH TIME ZONE,
    last_accessed_at TIMESTAMP WITH TIME ZONE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_guest_invites_token ON guest_review_invitations(token_hash);
CREATE INDEX idx_guest_invites_email ON guest_review_invitations(recipient_email);

-- 2. Email OTP Challenges (Free Verification via Hostinger SMTP)
CREATE TABLE email_otps (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    workspace_id UUID NOT NULL REFERENCES workspaces(id) ON DELETE CASCADE,
    email VARCHAR(255) NOT NULL,
    invitation_id UUID REFERENCES guest_review_invitations(id) ON DELETE CASCADE,
    otp_hash CHAR(64) NOT NULL, -- SHA-256 of 6-digit numeric OTP code
    attempts_count INT NOT NULL DEFAULT 0,
    max_attempts INT NOT NULL DEFAULT 5,
    expires_at TIMESTAMP WITH TIME ZONE NOT NULL,
    verified_at TIMESTAMP WITH TIME ZONE,
    ip_address_hash CHAR(64) NOT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_email_otps_lookup ON email_otps(email, expires_at) WHERE verified_at IS NULL;
CREATE INDEX idx_email_otps_invitation ON email_otps(invitation_id);

-- 3. Active Guest Sessions (Issued upon successful OTP verification)
CREATE TABLE guest_sessions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    invitation_id UUID NOT NULL REFERENCES guest_review_invitations(id) ON DELETE CASCADE,
    session_token_hash CHAR(64) NOT NULL UNIQUE,
    ip_address_hash CHAR(64) NOT NULL,
    user_agent VARCHAR(512),
    expires_at TIMESTAMP WITH TIME ZONE NOT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_guest_sessions_token ON guest_sessions(session_token_hash);
