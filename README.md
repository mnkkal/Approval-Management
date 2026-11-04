# ApexApprove: Open-Source Agency Client Approval Portal

[![Deploy to Hostinger](https://www.hostinger.com/deploy-to-hostinger.svg)](https://hostinger.com/vps?app=https://github.com/mnkkal/Approval-Management)
[![License: AGPL v3](https://img.shields.io/badge/License-AGPL_v3-blue.svg)](https://www.gnu.org/licenses/agpl-3.0)
[![CI Test Suite](https://github.com/mnkkal/Approval-Management/actions/workflows/ci.yml/badge.svg)](https://github.com/mnkkal/Approval-Management/actions/workflows/ci.yml)
[![Hostinger Open Source Partner](https://img.shields.io/badge/Hostinger-Open_Source_Partner-purple.svg)](https://hpanel.hostinger.com/open-source-partners)
[![PHP 8.3](https://img.shields.io/badge/PHP-8.3-777BB4.svg)](https://www.php.net/)
[![Docker Compose Ready](https://img.shields.io/badge/Docker-Compose_Ready-2496ED.svg)](https://www.docker.com/)

> **Maintainer:** Curve Metrics ([contact@curvemetrics.in](mailto:contact@curvemetrics.in))  
> **Source Repository:** [https://github.com/mnkkal/Approval-Management](https://github.com/mnkkal/Approval-Management)  
> **License:** GNU Affero General Public License v3.0 (AGPLv3)

**ApexApprove** is a production-ready, open-source client review and approval management portal engineered for creative agencies, branding studios, web developers, video teams, and freelancers. It eliminates review chaos across WhatsApp and email threads by providing an immutable, auditable system of record for deliverable sign-offs.

---

## ⚡ 1-Click Deploy on Hostinger

Deploy ApexApprove directly onto a high-performance Hostinger VPS in one click:

[![Deploy to Hostinger](https://www.hostinger.com/deploy-to-hostinger.svg)](https://hostinger.com/vps?app=https://github.com/mnkkal/Approval-Management)

### For Hostinger Open-Source Developers:
This repository is configured to integrate directly with [Hostinger's Open Source Partners Program](https://hpanel.hostinger.com/open-source-partners). 
- Connect `https://github.com/mnkkal/Approval-Management` in your hPanel dashboard.
- Users deploying this app to Hostinger Web Apps or VPS earn you recurring partner commissions.

---

## 🌟 Core Architecture & Capabilities

### 1. Zero-Cost Guest Review Verification (Email OTP)
- Clients do not need to create accounts or download applications.
- Review invitations trigger a **6-digit cryptographic numeric OTP** delivered via **Hostinger Business SMTP** (`contact@curvemetrics.in`).
- Only the `SHA-256` hash of the code is stored in the database.
- 15-minute token TTL, maximum 5 attempts, and strict rate-limiting.
- Built-in RFC 6238 TOTP authenticator-app support for registered agency staff.

### 2. Dual-Track Video Review Engine
- **Direct Web Playback (Default):** Native HTML5 streaming for standard web video (MP4 H.264/AAC, WebM) with frame-accurate timestamp annotations and scrubber pins.
- **Optional Local FFmpeg Worker:** Activate local video transcoding and thumbnail generation without any external paid transcoding API:
  ```bash
  docker compose --profile media-worker up -d
  ```
  *Protected with concurrency limits (1 job at a time) and 2048 MB RAM caps to prevent VPS starvation.*

### 3. Deterministic Version Approvals
- Explicit sign-offs bind immutably to the content hash (`SHA-256`) of the deliverable version.
- Forward-linked cryptographic ledger records approver email, IP hash, user-agent, and timestamp.
- **New Version Rule:** Uploading Version $N+1$ never inherits approval from Version $N$.

### 4. Separate Internal Notes & Client Feedback
- Threaded discussions maintain strict database separation (`is_internal = true`).
- Internal agency notes are physically omitted from client API payloads and guest views.

---

## 🚀 Quick Deployment Guide

### Prerequisites
- Docker Engine 24+ & Docker Compose v2+
- A VPS or server (e.g. Hostinger KVM VPS)

### Step 1: Clone Repository
```bash
git clone https://github.com/mnkkal/Approval-Management.git
cd Approval-Management
```

### Step 2: Configure Environment
```bash
cp .env.example .env
```
Edit `.env` and set your secure passwords:
- `DB_PASSWORD`: Strong password for PostgreSQL.
- `REDIS_PASSWORD`: Strong password for Redis.
- `MAIL_PASSWORD`: Your Hostinger email account password for `contact@curvemetrics.in`.

### Step 3: Start Containers
```bash
# Standard Light Deployment (Direct HTML5 video review)
docker compose up -d

# Optional: With Local FFmpeg Transcoding Worker
docker compose --profile media-worker up -d
```

### Step 4: Verify Deployment
Navigate to `http://your-server-ip/` or test the automated health probe:
```bash
curl http://localhost/up
# Response: {"status":"ok","service":"ApexApprove","version":"1.0.0-agpl"}
```

---

## 📂 Project Architecture

```
Approval-Management/
├── .env.example                                  # Environment template configured for Hostinger SMTP
├── docker-compose.yml                            # Production Docker stack (NGINX, PHP 8.3, Postgres, Redis)
├── LICENSE                                       # GNU Affero General Public License v3.0 (AGPLv3)
├── README.md                                     # Documentation & Hostinger Deploy Button
├── app/
│   └── Services/
│       ├── OtpVerificationService.php            # Free Email OTP generation & verification
│       ├── VideoReviewEngine.php                 # Direct HTML5 video & transcode router
│       └── ApprovalLedgerService.php             # SHA-256 bound immutable sign-offs
├── database/
│   └── migrations/
│       ├── 001_create_workspaces_and_users.sql   # Multi-tenancy, RBAC & TOTP
│       ├── 002_create_projects_and_deliverables.sql # Projects, deliverables & versions
│       ├── 003_create_guest_tokens_and_email_otps.sql # Guest tokens & OTP challenge ledger
│       └── 004_create_feedback_and_approvals.sql # Pin coordinates, threads & approvals
├── docker/
│   ├── entrypoint.sh                             # Container auto-initialization & migration runner
│   ├── nginx/default.conf                        # Reverse proxy, CSP & 2GB upload limits
│   ├── php/Dockerfile                            # PHP 8.3 FPM production image
│   └── workers/ffmpeg/Dockerfile                 # Optional FFmpeg media worker
├── public/
│   └── index.php                                 # Fast-path healthcheck, AGPL hook & UI
└── resources/
    └── js/
        └── Pages/
            └── DeliverableReview.tsx             # Interactive review canvas (pins & approval modal)
```

---

## 📧 Email Configuration (Hostinger Business SMTP)

The portal uses Hostinger's high-deliverability business SMTP out of the box:

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=smtp.hostinger.com
MAIL_PORT=465
MAIL_USERNAME=contact@curvemetrics.in
MAIL_PASSWORD=YOUR_HOSTINGER_PASSWORD_HERE
MAIL_ENCRYPTION=ssl
MAIL_FROM_ADDRESS="contact@curvemetrics.in"
MAIL_FROM_NAME="Curve Metrics Approval Portal"
```

---

## ⚖️ AGPLv3 Compliance

In accordance with Section 13 of the **GNU Affero General Public License v3.0**, every deployment provides an open source compliance endpoint accessible at `/source` or `/about/source`, directing users to the complete corresponding source code repository.

---

## 🤝 Community & Support

- **Bug Reports & Feature Requests:** [GitHub Issues](https://github.com/mnkkal/Approval-Management/issues)
- **Commercial Support & Managed Hosting:** [contact@curvemetrics.in](mailto:contact@curvemetrics.in)
- **Hostinger Partner Program:** [Hostinger Open Source Partners](https://hpanel.hostinger.com/open-source-partners)
