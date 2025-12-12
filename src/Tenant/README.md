# Tenant Module Documentation

Multi-tenancy module for **Fireguard Auth Server**.
Manages tenant configurations and isolation settings.

---

## Table of Contents

- [Overview](#overview)
- [API Endpoints](#api-endpoints)
- [Architecture](#architecture)
- [Developer Guide](#developer-guide)

---

<div align="right"><a href="#tenant-module-documentation">Back to top ⬆️</a></div>

## Overview

The Tenant module allows the Auth Server to support multiple organizations or "tenants". Each tenant can have its own specific configuration, effectively isolating data and settings.

### Features

| Feature | Description |
|---------|-------------|
| 🏢 Tenant Management | Create and configure tenants |
| ⚙️ Settings | Per-tenant token lifetimes, policy settings, etc. |
| 🌍 Isolation | Ensure data isolation between tenants (Logic) |

---

<div align="right"><a href="#tenant-module-documentation">Back to top ⬆️</a></div>

## API Endpoints

### Tenants

Manage tenants.

#### GET `/api/tenants`
List available tenants.

#### POST `/api/tenants`
Create a new tenant.

```json
{
  "name": "Acme Corp",
  "identifier": "acme",
  "settings": {
    "accessTokenTtl": 3600,
    "refreshTokenTtl": 86400
  }
}
```

#### GET `/api/tenants/{id}`
Get tenant details.

#### PATCH `/api/tenants/{id}`
Update tenant settings.

---

<div align="right"><a href="#tenant-module-documentation">Back to top ⬆️</a></div>

## Architecture

The module follows **Hexagonal Architecture** (Ports & Adapters) principles:

```
src/Tenant/
├── Application/              # Application Layer
│   ├── Port/                 # Ports (Interfaces)
│   ├── UseCase/              # Commands & Queries
│   └── Service/              # Application Services
│
├── Domain/                   # Domain Layer (Core)
│   ├── Model/                # Entities (Tenant)
│   ├── Repository/           # Repository Interfaces
│   └── ValueObject/          # Value Objects (TenantId, TenantSettings)
│
├── Infrastructure/           # Infrastructure Layer (Adapters)
│   └── Persistence/          # Doctrine Repositories
│
└── Presentation/             # Presentation Layer (API)
    ├── Api/                  # API Platform Resources
    └── Dto/                  # Data Transfer Objects
```

---

<div align="right"><a href="#tenant-module-documentation">Back to top ⬆️</a></div>

## Developer Guide

### Tests

| Test Type | Directory | Description |
|-----------|-----------|-------------|
| **Unit** | `tests/Unit/Tenant` | Domain logic tests |
| **E2E** | `tests/E2E/Tenant` | Tenant CRUD tests |
