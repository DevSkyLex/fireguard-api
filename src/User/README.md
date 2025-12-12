# User Module Documentation

User management module for **Fireguard Auth Server**.
Handles user registry, profile management, and CRUD operations.

---

## Table of Contents

- [Overview](#overview)
- [API Endpoints](#api-endpoints)
- [Architecture](#architecture)
- [Developer Guide](#developer-guide)

---

<div align="right"><a href="#user-module-documentation">Back to top ⬆️</a></div>

## Overview

The User module is responsible for the lifecycle of user accounts. It validates user data, ensures uniqueness constraints (email, username), and manages user profiles.

### Features

| Feature | Description |
|---------|-------------|
| 👤 User CRUD | Complete management (Create, Read, Update, Delete) |
| 🛡️ Validation | Enforces strong passwords and valid emails |
| 🔒 Security | Password hashing via Symfony PasswordHasher |
| 🆔 Identification | UUID based user identification |
| 🏢 Tenant Aware | Users can be scoped to tenants (if enabled) |

---

<div align="right"><a href="#user-module-documentation">Back to top ⬆️</a></div>

## API Endpoints

### Users

Manage user accounts.

#### GET `/api/users`
List filtered users.

#### POST `/api/users`
Create a new user.

```json
{
  "email": "jane.doe@example.com",
  "username": "janedoe",
  "plainPassword": "SecurePassword123!",
  "firstName": "Jane",
  "lastName": "Doe"
}
```

#### GET `/api/users/{id}`
Get user details.

#### PATCH `/api/users/{id}`
Update user profile (e.g., name, email).

#### DELETE `/api/users/{id}`
Delete a user account.

---

<div align="right"><a href="#user-module-documentation">Back to top ⬆️</a></div>

## Architecture

The module follows **Hexagonal Architecture** (Ports & Adapters) principles:

```
src/User/
├── Application/              # Application Layer
│   ├── Port/                 # Ports (Interfaces)
│   ├── UseCase/              # Commands & Queries
│   │   ├── Command/          # e.g., CreateUser, UpdateUser
│   │   └── Query/            # e.g., GetUser
│   └── Service/              # Application Services
│
├── Domain/                   # Domain Layer (Core)
│   ├── Model/                # Entities (User)
│   ├── Repository/           # Repository Interfaces
│   └── ValueObject/          # Value Objects (Email, Username, Password)
│
├── Infrastructure/           # Infrastructure Layer (Adapters)
│   ├── Persistence/          # Doctrine Repositories
│   └── Console/              # CLI Commands (app:user:create)
│
└── Presentation/             # Presentation Layer (API)
    ├── Api/                  # API Platform Resources
    └── Dto/                  # Data Transfer Objects
```

---

<div align="right"><a href="#user-module-documentation">Back to top ⬆️</a></div>

## Developer Guide

### CLI Commands

| Command | Description |
|---------|-------------|
| `php bin/console app:user:create` | Create a new user interactively |

### Tests

| Test Type | Directory | Description |
|-----------|-----------|-------------|
| **Unit** | `tests/Unit/User` | Domain validation rules |
| **E2E** | `tests/E2E/User` | User CRUD flow tests |
