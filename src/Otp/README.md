# OTP Module Documentation

Generic One-Time Password module for **Fireguard Auth Server**.
Handles generation, delivery, and verification of OTPs for various purposes (MFA, Email Verification, etc.).

---

## Table of Contents

- [Overview](#overview)
- [API Endpoints](#api-endpoints)
- [Supported Channels](#supported-channels)
- [Architecture](#architecture)
- [Developer Guide](#developer-guide)

---

<div align="right"><a href="#otp-module-documentation">Back to top ⬆️</a></div>

## Overview

The OTP module is designed to be usage-agnostic. It creates temporary codes linked to a specific context (user, action) and channel.

### Features

| Feature | Description |
|---------|-------------|
| 🎲 Generation | Cryptographically secure random code generation |
| 📨 Delivery | Multi-channel delivery (Email, SMS, TOTP) |
| 🛡️ Verification | Strict verification logic with attempts limiting |
| ⏱️ TTL | Configurable Time-To-Live for codes |
| 🧩 Contextual | Codes are bound to specific actions (e.g., `login_mfa`, `reset_password`) |

---

<div align="right"><a href="#otp-module-documentation">Back to top ⬆️</a></div>

## API Endpoints

### Challenges

Review `Auth` module for MFA specific flows. The OTP module exposes generic endpoints for internal use or specific flows.

#### POST `/api/otp/challenges`
Create a new OTP challenge.

```json
{
  "context": "verification",
  "identifier": "user@example.com",
  "channel": "email"
}
```

#### POST `/api/otp/verify`
Generic verification endpoint.

```json
{
  "challenge_id": "uuid",
  "code": "123456"
}
```

---

<div align="right"><a href="#otp-module-documentation">Back to top ⬆️</a></div>

## Supported Channels

The module supports multiple delivery channels via Adapters:

1.  **Email**: Sends code via Symfony Mailer/Notifier.
2.  **SMS**: Sends code via SMS providers (Twilio, etc.).
3.  **TOTP**: Time-based OTP (Authenticator Apps like Google Authenticator).

### Configuration

```env
OTP_CODE_LENGTH=6
OTP_TTL=300
```

---

<div align="right"><a href="#otp-module-documentation">Back to top ⬆️</a></div>

## Architecture

The module follows **Hexagonal Architecture** (Ports & Adapters) principles:

```
src/Otp/
├── Application/              # Application Layer
│   ├── Port/                 # Ports (Interfaces)
│   ├── UseCase/              # Commands & Queries
│   │   ├── Command/          # e.g., GenerateOtp, VerifyOtp
│   │   └── Query/            # Read operations
│   └── Service/              # Application Services
│
├── Domain/                   # Domain Layer (Core)
│   ├── Model/                # Entities (OtpChallenge)
│   ├── Factory/              # OTP Generation Logic
│   ├── Repository/           # Repository Interfaces
│   └── ValueObject/          # Value Objects (OtpCode, Channel)
│
├── Infrastructure/           # Infrastructure Layer (Adapters)
│   ├── Adapter/              # Channel Adapters (EmailAdapter, TotpAdapter)
│   ├── Persistence/          # Doctrine Repositories
│   └── Symfony/              # Framework integration
│
└── Presentation/             # Presentation Layer (API)
    ├── Api/                  # API Platform Resources
    └── Dto/                  # Data Transfer Objects
```

---

<div align="right"><a href="#otp-module-documentation">Back to top ⬆️</a></div>

## Developer Guide

### Tests

| Test Type | Directory | Description |
|-----------|-----------|-------------|
| **Unit** | `tests/Unit/Otp` | Domain logic and Generator tests |
| **E2E** | `tests/E2E/Otp` | Challenge flow verification |

### Adding a Channel

1.  Implement `ChannelAdapterInterface` in `Infrastructure/Adapter`.
2.  Register the new adapter in the service container.
3.  Add the new channel type to the `Channel` Value Object.
