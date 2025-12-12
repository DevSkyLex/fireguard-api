# Fireguard Auth Server

Modular Authentication and Authorization Server implementing OAuth2 and OpenID Connect key standards.

## 📚 Documentation by Module

- 🔐 [Auth module](src/Auth/README.md) - Authentication & MFA
- 👮 [Authorization module](src/Authorization/README.md) - RBAC & Permissions
- 🏢 [Client module](src/Client/README.md) - OAuth2 Clients
- 📨 [Otp module](src/Otp/README.md) - One-Time Password engine
- 📱 [Session module](src/Session/README.md) - User session tracking
- 🏘️ [Tenant module](src/Tenant/README.md) - Multi-tenancy
- 🛡️ [TrustedDevice module](src/TrustedDevice/README.md) - Trusted devices management
- 👤 [User module](src/User/README.md) - User management
- 🧩 [Shared module](src/Shared/README.md) - Core components

## 🛠️ Quick Start

### Prerequisites
- PHP 8.2+
- Docker & Docker Compose
- Make

### Installation

```bash
make install
```

## 📊 Code Quality & Analysis

The project integrates [SonarQube](https://www.sonarqube.org/) for continuous code quality inspection.

### Running Local Analysis

1.  **Start SonarQube Server**:
    One-time setup to launch the local container.
    ```bash
    make sonar-up
    ```
    *Access Dashboard: http://localhost:9000 (Login: admin / admin)*

2.  **Run Analysis**:
    Executes the scanner against your local code.
    ```bash
    make sonar-scan
    ```

## ✅ Testing

```bash
make test
```
