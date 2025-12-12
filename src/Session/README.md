# Session Module Documentation

Session management module for **Fireguard Auth Server**.
Handles user sessions, device tracking, and revocation.

---

## Table of Contents

- [Overview](#overview)
- [API Endpoints](#api-endpoints)
- [Architecture](#architecture)
- [Developer Guide](#developer-guide)

---

<div align="right"><a href="#session-module-documentation">Back to top ⬆️</a></div>

## Overview

The Session module tracks active user sessions across different devices and clients. It provides visibility into where a user is logged in and allows for remote logout (session revocation).

### Features

| Feature | Description |
|---------|-------------|
| 🕵️ Tracking | Tracks IP address, User Agent, and login time |
| 📱 Device Info | Identifies device type and browser |
| ❌ Revocation | Revoke specific sessions or all sessions for a user |
| 🔄 Synchronization | Syncs with Auth module token events |

---

<div align="right"><a href="#session-module-documentation">Back to top ⬆️</a></div>

## API Endpoints

### Sessions

Manage user sessions.

#### GET `/api/sessions`
List all active sessions for the current user.

**Response:**
```json
[
  {
    "id": "uuid",
    "ip_address": "192.168.1.1",
    "user_agent": "Mozilla/5.0 ...",
    "last_active": "2023-10-27T10:00:00Z",
    "is_current": true
  }
]
```

#### DELETE `/api/sessions/{id}`
Revoke a specific session.

#### DELETE `/api/sessions`
Revoke all sessions (except current one optionally).

---

<div align="right"><a href="#session-module-documentation">Back to top ⬆️</a></div>

## Architecture

The module follows **Hexagonal Architecture** (Ports & Adapters) principles:

```
src/Session/
├── Application/              # Application Layer
│   ├── Port/                 # Ports (Interfaces)
│   ├── UseCase/              # Commands & Queries
│   │   ├── Command/          # e.g., RevokeSession
│   │   └── Query/            # e.g., GetUserSessions
│   └── Service/              # Application Services
│
├── Domain/                   # Domain Layer (Core)
│   ├── Model/                # Entities (Session)
│   ├── Repository/           # Repository Interfaces
│   └── ValueObject/          # Value Objects (SessionId, DeviceInfo)
│
├── Infrastructure/           # Infrastructure Layer (Adapters)
│   └── Persistence/          # Doctrine Repositories
│
└── Presentation/             # Presentation Layer (API)
    ├── Api/                  # API Platform Resources
    └── Dto/                  # Data Transfer Objects
```

---

<div align="right"><a href="#session-module-documentation">Back to top ⬆️</a></div>

## Developer Guide

### Tests

| Test Type | Directory | Description |
|-----------|-----------|-------------|
| **Unit** | `tests/Unit/Session` | Domain logic tests |
| **E2E** | `tests/E2E/Session` | Session management flow tests |
