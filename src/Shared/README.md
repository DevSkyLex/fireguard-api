# Shared Module Documentation

Shared kernel for **Fireguard Auth Server**.
Contains reusable components, Value Objects, Logic, and Interfaces used across different modules.

---

## Table of Contents

- [Overview](#overview)
- [Components](#components)
- [Architecture](#architecture)
- [Developer Guide](#developer-guide)

---

<div align="right"><a href="#shared-module-documentation">Back to top ⬆️</a></div>

## Overview

The Shared module acts as the "Standard Library" for the application. It ensures consistency by providing common types (Email, UUID), infrastructure adapters (Mailer, Translator), and base classes.

### Responsibilities

| Responsibility | Description |
|----------------|-------------|
| 🧬 Value Objects | Common types (`Email`, `Uuid`, `UserAgent`) |
| 🏗️ Base Classes | `AggregateRoot`, `DomainEvent` |
| 🔌 Adapters | `MailerAdapter`, `TranslatorAdapter`, `UuidGenerator` |
| 🛡️ Exceptions | Standardized exception hierarchy |

---

<div align="right"><a href="#shared-module-documentation">Back to top ⬆️</a></div>

## Components

### Domain Layer
*   **Value Objects**: Fundamental types used throughout the domain.
    *   `Uuid`: Identity management.
    *   `Email`: Email validation and normalization.
    *   `DateTime`: Time handling.
*   **Events**: Base event system for Domain Events.

### Infrastructure Layer
*   **Symfony Adapters**: Bridges between Domain Ports and Symfony Components (Mailer, RequestStack, etc.).
*   **Doctrine Types**: Custom DBAL types for Value Objects.

---

<div align="right"><a href="#shared-module-documentation">Back to top ⬆️</a></div>

## Architecture

The module follows **Hexagonal Architecture** (Ports & Adapters) principles:

```
src/Shared/
├── Application/              # Application Layer
│   ├── Port/                 # Ports (Interfaces)
│   └── Service/              # Shared Application Services
│
├── Domain/                   # Domain Layer (Core)
│   ├── Model/                # Base Entities
│   ├── ValueObject/          # Common Value Objects
│   └── Event/                # Event Infrastructure
│
├── Infrastructure/           # Infrastructure Layer (Adapters)
│   ├── Symfony/              # Symfony Framework Adapters
│   ├── Persistence/          # Doctrine Custom Types
│   └── Exception/            # Shared Exceptions
│
└── Presentation/             # Presentation Layer (API)
    └── Api/                  # API Platform Extensions
```

---

<div align="right"><a href="#shared-module-documentation">Back to top ⬆️</a></div>

## Developer Guide

> [!NOTE]
> Avoid adding business logic to `Shared`. It should only contain generic, reusable code.

### Tests

| Test Type | Directory | Description |
|-----------|-----------|-------------|
| **Unit** | `tests/Unit/Shared` | Value Objects and Adapters tests |
