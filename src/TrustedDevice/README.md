# Trusted Device Module Documentation

Trusted Device management for **Fireguard Auth Server**.
Allows users to mark devices as trusted to skip MFA or enhance security context.

---

## Table of Contents

- [Overview](#overview)
- [API Endpoints](#api-endpoints)
- [Architecture](#architecture)
- [Developer Guide](#developer-guide)

---

<div align="right"><a href="#trusted-device-module-documentation">Back to top ⬆️</a></div>

## Overview

A "Trusted Device" is a device (identified by a unique fingerprint) that the user has explicitly authorized. This status can be used to bypass MFA challenges on subsequent logins (if configured) or simply to provide a list of known devices.

### Features

| Feature | Description |
|---------|-------------|
| 📜 Trust Listing | List trusted devices for a user |
| 🛡️ Security | Stores device metadata (IP, Location, User Agent) |
| ❌ Revocation | Remove trust status from a device |
| 🍪 Identification | Uses long-lived secure cookies or tokens for identification |

---

<div align="right"><a href="#trusted-device-module-documentation">Back to top ⬆️</a></div>

## API Endpoints

### Trusted Devices

Manage trusted devices.

#### GET `/api/trusted-devices`
List all trusted devices for the authenticated user.

**Response:**
```json
[
  {
    "id": "uuid",
    "name": "My MacBook Pro",
    "platform": "macOS",
    "browser": "Chrome",
    "created_at": "2023-10-01T12:00:00Z"
  }
]
```

#### DELETE `/api/trusted-devices/{id}`
Remove a trusted device. The next login from this device will require MFA (if enabled).

#### DELETE `/api/trusted-devices`
Revoke all trusted devices.

---

<div align="right"><a href="#trusted-device-module-documentation">Back to top ⬆️</a></div>

## Architecture

The module follows **Hexagonal Architecture** (Ports & Adapters) principles:

```
src/TrustedDevice/
├── Application/              # Application Layer
│   ├── Port/                 # Ports (Interfaces)
│   ├── UseCase/              # Commands & Queries
│   │   ├── Command/          # e.g., RevokeTrustedDevice
│   │   └── Query/            # e.g., ListTrustedDevices
│   └── Service/              # Application Services
│
├── Domain/                   # Domain Layer (Core)
│   ├── Model/                # Entities (TrustedDevice)
│   ├── Repository/           # Repository Interfaces
│   └── ValueObject/          # Value Objects (DeviceId)
│
├── Infrastructure/           # Infrastructure Layer (Adapters)
│   └── Persistence/          # Doctrine Repositories
│
└── Presentation/             # Presentation Layer (API)
    ├── Api/                  # API Platform Resources
    └── Dto/                  # Data Transfer Objects
```

---

<div align="right"><a href="#trusted-device-module-documentation">Back to top ⬆️</a></div>

## Developer Guide

### Tests

| Test Type | Directory | Description |
|-----------|-----------|-------------|
| **Unit** | `tests/Unit/TrustedDevice` | Domain logic tests |
| **E2E** | `tests/E2E/TrustedDevice` | API flow tests |
