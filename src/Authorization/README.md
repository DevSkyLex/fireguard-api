# Authorization Module Documentation

Authorization module for **Fireguard Auth Server**.
Manages Role-Based Access Control (RBAC) and Permission-Based Access Control.

---

## Table of Contents

- [Overview](#overview)
- [API Endpoints](#api-endpoints)
- [Access Control](#access-control)
- [Architecture](#architecture)
- [Developer Guide](#developer-guide)

---

<div align="right"><a href="#authorization-module-documentation">Back to top ⬆️</a></div>

## Overview

The Authorization module handles the assignment of roles to users and the definition of permissions. It provides the mechanism to verify if a user has the necessary rights to perform an action.

### Features

| Feature | Description |
|---------|-------------|
| 👮 Roles | Definition of user roles (e.g., ROLE_USER, ROLE_ADMIN) |
| 🔑 Permissions | Granular permissions (e.g., user:read, user:write) |
| 🛡️ Voters | Symfony Security Voters for checking access rights |
|  hierarchy | Role hierarchy management |
| 📋 Management | CRUD operations for Roles and Permissions |

---

<div align="right"><a href="#authorization-module-documentation">Back to top ⬆️</a></div>

## API Endpoints

### Roles

Manage system roles.

#### GET `/api/roles`
List all defined roles.

#### POST `/api/roles`
Create a new role.

```json
{
  "name": "ROLE_EDITOR",
  "description": "Can edit content",
  "permissions": ["post:write", "post:edit"]
}
```

### Permissions

Manage granular permissions.

#### GET `/api/permissions`
List available permissions.

---

<div align="right"><a href="#authorization-module-documentation">Back to top ⬆️</a></div>

## Access Control

The module integrates with Symfony's Security component using Voters.

### Checking Attributes

You can check permissions in your code or configuration:

**In Controller:**
```php
$this->denyAccessUnlessGranted('post:edit');
```

**In Twig:**
```twig
{% if is_granted('post:edit') %}
    <a href="...">Edit</a>
{% endif %}
```

**Via Attributes:**
```php
#[IsGranted('post:edit')]
public function edit(): Response { ... }
```

---

<div align="right"><a href="#authorization-module-documentation">Back to top ⬆️</a></div>

## Architecture

The module follows **Hexagonal Architecture** (Ports & Adapters) principles:

```
src/Authorization/
├── Application/              # Application Layer
│   ├── Port/                 # Ports (Interfaces)
│   ├── UseCase/              # Commands & Queries
│   └── Service/              # Application Services
│
├── Domain/                   # Domain Layer (Core)
│   ├── Model/                # Entities (Role, Permission)
│   ├── Repository/           # Repository Interfaces
│   └── Service/              # Domain Services (RoleManager)
│
├── Infrastructure/           # Infrastructure Layer (Adapters)
│   ├── Persistence/          # Doctrine Repositories
│   └── Security/
│       └── Voter/            # Symfony Security Voters
│
└── Presentation/             # Presentation Layer (API)
    ├── Api/                  # API Platform Resources
    └── Console/              # CLI Commands
```

---

<div align="right"><a href="#authorization-module-documentation">Back to top ⬆️</a></div>

## Developer Guide

### Tests

| Test Type | Directory | Description |
|-----------|-----------|-------------|
| **Unit** | `tests/Unit/Authorization` | Domain logic and Voters isolation tests |
| **E2E** | `tests/E2E/Authorization` | API CRUD and permission enforcement tests |

To run authorization tests:
```bash
make test tests/Unit/Authorization
```

### Adding a Permission

1.  Create the permission via API or Migration.
2.  Assign it to appropriate Roles.
3.  Use the permission string (e.g., `resource:action`) in `is_granted()` checks.
