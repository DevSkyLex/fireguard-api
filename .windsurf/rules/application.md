---
trigger: always_on
---

## Purpose
Coordinate use cases; call Domain; call outbound ports; return simple results. No framework coupling.

## Structure
- `UseCase/Command`: `{Action}Command`, `{Action}Handler`, `{Action}Result`.
- `UseCase/Query`: `{Query}`, `{Query}Handler`, `{Query}Result`.
- `Port/Outbound`: `{Aggregate}RepositoryPort`, `ClockPort`, `TransactionManagerPort`, etc.
- `Security`: per-use-case access policy (not HTTP-layer).

## Rules
- Handlers are thin; all mutations go through aggregate methods.
- Inject only ports and domain services; never EntityManager.
- Use `TransactionManagerPort` for write commands.
- Validate `Command` input early; never trust Presentation.

## Command Template
```php
final readonly class RegisterUserCommand { public function __construct(
    public string $email, public string $givenName, public string $familyName){}
}
final class RegisterUserHandler {
    public function __construct(
        private UserRepositoryPort $users,
        private ClockPort $clock,
        private TransactionManagerPort $tx){}
    public function __invoke(RegisterUserCommand $c): RegisterUserResult {
        return $this->tx->transactional(function() use ($c) {
            // domain orchestration here
            // $user = User::register(...)
            // $this->users->save($user);
            return new RegisterUserResult(/* ... */);
        });
    }
}
```
