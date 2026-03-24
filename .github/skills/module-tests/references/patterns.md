# Module Test Patterns

## Unit tests

- use `TestCase`
- use `#[CoversClass]` and `#[Test]`
- mock ports and external adapters only
- assert results and exceptions rather than internals

## Integration tests

- use `KernelTestCase`
- pull the correct entity manager from the container
- verify scoped reads and writes
- close the entity manager in `tearDown`

## Functional tests

- use `WebTestCase`
- assert status codes and response shapes
- assert `401` or `403` for unauthenticated or forbidden access when relevant
- prefer contract assertions over implementation-coupled assertions
- for security-sensitive endpoints, also assert the route exists and does not accidentally return `404`
- add a `429` expectation only when the endpoint is actually protected by a rate limiter
