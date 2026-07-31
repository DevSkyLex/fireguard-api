---
paths:
  - 'config/modules/*.yaml'
  - 'config/packages/*.yaml'
  - 'config/packages/**/*.yaml'
---

# Service and package configuration

## The `$entityManager` argument — the silent bug

**Every repository, processor, and provider that touches Doctrine must name its entity manager explicitly:**

```yaml
Facility\Infrastructure\Persistence\Doctrine\Repository\FacilityRepository:
  arguments:
    $entityManager: '@doctrine.orm.main_entity_manager'
```

Autowiring resolves `EntityManagerInterface` to the **default** manager. Omit the argument and the code compiles, `phpstan` passes, `deptrac` passes, `lint:container` passes — and it queries the wrong database. No exception, no failing test, just missing data found much later.

`auth` owns OAuth · User · Otp · Authorization · Session · Tenant · TrustedDevice · Audit. `main` owns the business modules. **`config/packages/doctrine.yaml` is the authority** — find the `dir:`/`prefix:` pair that maps the module's `Record` namespace.

## Ports need two entries

```yaml
Facility\Application\Port\Outbound\FacilityRepositoryPort:
  alias: Facility\Infrastructure\Persistence\Doctrine\Repository\FacilityRepository
```

The `alias:` binds the port; the `arguments:` above name the manager. A missing alias is invisible to phpstan and deptrac — **`make lint` (`lint:container`) is what catches it**, and `debug:container <FQCN>` proves it resolves.

## security.yaml

- A new module's endpoints deny by **explicit rule**, not by omission.
- Access control is **first-match-wins**, and overlapping patterns rarely read the way they resolve. Verify with `php -d memory_limit=1G bin/console debug:firewall`, which shows what Symfony actually applies.
- Public endpoints stay explicit and few.

## Rate limiting

Define limiters in `config/packages/rate_limiter.yaml` and inject with `#[Autowire(service: 'limiter.<name>')]`. Login, OTP, password reset, registration, and token endpoints need one — they are cheap to hammer.

## Everything else

Env-driven configuration belongs in `config/packages` or `.env`; **never commit a secret** — `.env*` and `config/jwt/` are blocked by a hook. Validate any change with `make lint`, which runs `lint:container` and `lint:yaml config --parse-tags`.
