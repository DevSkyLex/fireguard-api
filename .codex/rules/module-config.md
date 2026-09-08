# Service and package configuration

Every repository, processor or provider that touches Doctrine names its entity manager
in `config/modules/<module>.yaml`. Confirm ownership from the Record namespace mapping
in `config/packages/doctrine.yaml`; default autowiring selects auth and can silently send
business data to the wrong database.

A port needs its alias to the adapter and the adapter's explicit `$entityManager` when
Doctrine is involved. Validate both with `make lint` and `debug:container <FQCN>`.

New endpoints deny explicitly in `security.yaml`. Access rules and firewalls are
first-match-wins; inspect them in order. Public routes remain few and explicit. Put
rate limiters in `config/packages/rate_limiter.yaml` for authentication, OTP, password
reset, registration and token operations.

Environment-driven configuration belongs in package configuration or documented public
environment examples. Never commit secrets or JWT material.
