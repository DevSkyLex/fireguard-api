# Client Module Documentation

Client management module for **Fireguard Auth Server**.
Manages OAuth2 Clients (Applications) that can request tokens.

---

## Table of Contents

- [Overview](#overview)
- [API Endpoints](#api-endpoints)
- [Architecture](#architecture)
- [Developer Guide](#developer-guide)

---

<div align="right"><a href="#client-module-documentation">Back to top ⬆️</a></div>

## Overview

The Client module allows creation and management of OAuth2 clients. Each client represents an application (e.g., Frontend App, Mobile App, Backend Service) that needs to interact with the Auth Server.

### Features

| Feature | Description |
|---------|-------------|
| 🏢 Client Management | Create, Update, Delete OAuth2 clients |
| 🔑 Credentials | Manage Client ID and Client Secret |
| 🌐 Redirect URIs | Whitelist allowed redirect URIs for callbacks |
| 🔓 Grant Types | Configure allowed OAuth2 grant types per client |
| 🎚️ Scopes | Define allowed scopes for each client |

---

<div align="right"><a href="#client-module-documentation">Back to top ⬆️</a></div>

## API Endpoints

### Clients

Manage OAuth2 clients.

#### GET `/api/clients`
List all registered clients.

#### POST `/api/clients`
Register a new client.

```json
{
  "name": "My Angular App",
  "redirectUris": ["https://app.example.com/callback"],
  "allowedGrantTypes": ["authorization_code", "refresh_token"],
  "allowedScopes": ["openid", "profile", "email"],
  "isConfidential": false
}
```

#### GET `/api/clients/{id}`
Get details of a specific client.

#### PATCH `/api/clients/{id}`
Update client configuration.

#### DELETE `/api/clients/{id}`
Remove a client.

---

<div align="right"><a href="#client-module-documentation">Back to top ⬆️</a></div>

## Architecture

The module follows **Hexagonal Architecture** (Ports & Adapters) principles:

```
src/Client/
├── Application/              # Application Layer
│   ├── Port/                 # Ports (Interfaces)
│   ├── UseCase/              # Commands & Queries
│   │   ├── Command/          # e.g., CreateClient
│   │   └── Query/            # e.g., GetClient
│   └── Service/              # Application Services
│
├── Domain/                   # Domain Layer (Core)
│   ├── Model/                # Entities (Client)
│   ├── Repository/           # Repository Interfaces
│   ├── ValueObject/          # Value Objects (ClientId, RedirectUri)
│   └── Event/                # Domain Events
│
├── Infrastructure/           # Infrastructure Layer (Adapters)
│   ├── Persistence/          # Doctrine Repositories
│   └── Console/              # CLI Commands (e.g. app:client:create)
│
└── Presentation/             # Presentation Layer (API)
    ├── Api/                  # API Platform Resources
    └── Dto/                  # Data Transfer Objects
```

---

<div align="right"><a href="#client-module-documentation">Back to top ⬆️</a></div>

## Developer Guide

### CLI Commands

The module provides console commands for managing clients, useful for bootstrapping the system.

| Command | Description |
|---------|-------------|
| `php bin/console app:client:create` | Create a new OAuth2 client interactively |
| `php bin/console app:client:list` | List existing clients |

### Testing

| Test Type | Directory | Description |
|-----------|-----------|-------------|
| **Unit** | `tests/Unit/Client` | Domain logic tests |
| **E2E** | `tests/E2E/Client` | API flow tests |

To run client tests:
```bash
make test tests/Unit/Client tests/E2E/Client
```
