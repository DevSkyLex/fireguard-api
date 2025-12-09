# Fireguard Auth - AI Developer Guidelines

This document outlines the coding standards, architectural patterns, and best practices for the **Fireguard Auth** project. All AI agents and developers **MUST** adhere to these guidelines to maintain codebase consistency and architectural integrity.

## 1. Technology Stack

*   **Language**: PHP 8.4+
*   **Framework**: Symfony 7.3+
*   **API Framework**: API Platform 4.2+
*   **Persistence**: Doctrine ORM 3.x
*   **Static Analysis**: PHPStan (Level 7)
*   **Architecture**: Strict Hexagonal Architecture (Ports & Adapters)

## 2. Hexagonal Architecture Rules

The application is divided into strict layers. Dependencies flow **inwards** (or are inverted via interfaces).

### Layer Definitions

1.  **Domain (`src/{Module}/Domain/`)**
    *   **Purpose**: Inner-most layer. Contains business logic, entities, value objects, and domain events.
    *   **Dependencies**: ZERO dependencies on outer layers (Framework, DB, etc.). Only depends on `Shared/Domain`.
    *   **Components**: Aggregates, Entities, Value Objects, Domain Exceptions, Repository Interfaces (Inbound Ports - *rare* usually in App), Domain Services.

2.  **Application (`src/{Module}/Application/`)**
    *   **Purpose**: Orchestrates domain logic. Implements Use Cases.
    *   **Dependencies**: Depends on `Domain`. Defines interfaces (Ports) that Infrastructure implements.
    *   **Components**: Command/Query Handlers, DTOs (Commands/Queries/Results), Outbound Ports (Repository Interfaces, External Services), Event Listeners.

3.  **Infrastructure (`src/{Module}/Infrastructure/`)**
    *   **Purpose**: Implements technical details and interfaces defined in Application.
    *   **Dependencies**: Depends on `Application`, `Domain`, and framework libraries.
    *   **Components**: Doctrine Repositories, Persistence Models (Records), Mappers, Console Commands, Adapters (Mailer, Bus), Data Fixtures.

4.  **Presentation (`src/{Module}/Presentation/`)**
    *   **Purpose**: Entry points to the system.
    *   **Dependencies**: Depends on `Application`, `Domain`.
    *   **Components**: API Platform Resources, DTOs (Input/Output), Processors, Providers, Controllers (if raw Symfony).

### Shared Kernel (`src/Shared/`)
*   Contains reusable code (Value Objects, Base Classes, Traits).
*   Follows the same layered structure.
*   **Rule**: Modules (User, Auth, etc.) can depend on `Shared` but `Shared` CANNOT depend on Modules.

## 3. Coding Standards & Conventions

*   **Strict Types**: `declare(strict_types=1);` is **MANDATORY** in every file.
*   **Readonly Classes**: Use `readonly class` for immutable objects (DTOs, Value Objects, Commands, Queries).
*   **Constructor Promotion**: Use constructor property promotion visibility modifiers.
*   **Attributes**: Use PHP 8 Attributes (`#[]`) for mapping and configuration instead of Annotations/XML/YAML.
*   **Final by Default**: Classes should be `final` unless explicitly designed for inheritance.

## 4. Implementation Guidelines

### Domain Layer
*   **Rich Models**: Entities should encapsulate logic (methods like `register()`, `activate()`), not just getters/setters.
*   **Value Objects**: Use VOs heavily (e.g., `UserId`, `Email`, `Username`) instead of primitives. Define them in `src/Shared/Domain/ValueObject` or module domain.
*   **Factories**: Use static factory methods for creation (e.g., `User::register(...)`).
*   **Events**: Use `RecordsDomainEvents` trait. Release events in Handlers.

### Application Layer (CQRS)
*   **Separation**: STRICT separation between **Commands** (Write) and **Queries** (Read).
*   **Handlers**:
    *   One conceptual operation = One Handler class (e.g., `CreateUserHandler`).
    *   Implement `__invoke(Command $command): Result`.
    *   **NEVER** return Domain Entities directly in the Result. Return primitive IDs or DTOs.
*   **Ports**: Define interfaces for strict decoupling (e.g., `UserRepositoryPort`).

### Infrastructure Layer (Persistence)
*   **Decoupling**: Complete separation between **Domain Entities** and **Doctrine Records**.
    *   `Domain\Model\User`: Pure PHP Class.
    *   `Infrastructure\...\Record\UserRecord`: Doctrine Entity with `#[ORM\Column]`.
*   **Mappers**: Create generic Mappers (e.g., `UserMapper`) to translate between Domain Entities and Persistence Records using Reflection (to hydrate private properties).
*   **Repositories**: Implement outbound ports using Doctrine `EntityManager`, interacting with `Records` and mapping them to `Domain Entities`.

### Presentation Layer (API Platform)
*   **Resources**: Define `#[ApiResource]` classes in `Presentation\Api\Resource`. These are "Dummy" classes just for config.
*   **DTOs**: Use specific Input and Output DTOs (e.g., `UserInput`, `UserOutput`). **NEVER** expose Domain Entities or Records directly.
*   **State Processors**: Implement `ProcessorInterface` to handle writes. Dispatch Commands to the Application layer.
*   **State Providers**: Implement `ProviderInterface` to handle reads. Dispatch Queries to the Application layer.
*   **Serialization**: Use Groups (e.g., `UserSerializationGroup::READ`) to control output fields.

## 5. Testing Strategy

*   **Unit Tests (`tests/Unit/`)**: Test Domain logic and isolation. Mock all dependencies.
*   **Integration Tests (`tests/Integration/`)**: Test Infrastructure implementations (Repositories) with a real database.
*   **Functional Tests (`tests/Functional/`)**: Test API endpoints using API Platform's test client.
*   **Architecture Tests (`tests/Architecture/`)**: Use `phpat` to enforce dependency rules between layers.

## 6. Example Workflow: "Create User"

1.  **API**: `POST /users` -> `UserResource` config.
2.  **Processor**: `CreateUserProcessor` receives `UserInput` DTO.
3.  **Command**: Processor maps DTO to `CreateUserCommand`.
4.  **Bus**: Dispatch Command.
5.  **Handler**: `CreateUserHandler` receives Command.
    *   Generate `UserId` (UUID).
    *   Hash Password (`HashingPort`).
    *   Create `User` Domain Entity (`User::register()`).
    *   Call `UserRepositoryPort->save($user)`.
    *   Release Domain Events.
    *   Return `CreateUserResult`.
6.  **Repository**: `UserRepository->save()`:
    *   Map `User` Domain Entity -> `UserRecord`.
    *   Persist `UserRecord` via Doctrine.
7.  **Response**: Processor maps Result to `UserOutput` DTO.

## 7. Directory Structure (User Module Example)

```
src/User/
├── Application/
│   ├── EventHandler/
│   ├── Port/
│   │   └── Outbound/              # Interfaces (UserRepositoryPort)
│   └── UseCase/
│       ├── Command/
│       │   └── CreateUser/        # Command, Handler, Result
│       └── Query/
├── Domain/
│   ├── Event/
│   ├── Exception/
│   ├── Model/                     # User (Aggregate Root)
│   └── ValueObject/
├── Infrastructure/
│   ├── Console/
│   └── Persistence/
│       └── Doctrine/
│           ├── Mapper/            # UserMapper
│           ├── Record/            # UserRecord (ORM Entity)
│           └── Repository/        # UserRepository impl
└── Presentation/
    └── Api/
        ├── Dto/                   # UserInput, UserOutput
        ├── Processor/
        ├── Provider/
        └── Resource/              # UserResource
```
