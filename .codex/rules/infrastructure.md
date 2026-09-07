# Infrastructure layer

Infrastructure implements Application outbound ports. It owns Doctrine Records,
repositories, mappers and vendor adapters; it does not own business policy.

- A persistence repository implements an Application port and maps between Records
  and Domain or Application types. It never returns Doctrine objects through a port.
- Vendor SDK types remain inside adapters. Translate failures into project contracts
  or exceptions at the boundary.
- Every Doctrine repository and adapter names its entity manager explicitly in
  `config/modules/<module>.yaml`. Confirm the correct auth/main mapping in
  `config/packages/doctrine.yaml`; never rely on the default manager.
- Cross-module access uses published Application ports/contracts, never a sibling's
  Record, repository or Domain model.
- Persistence changes require a new migration under the correct database history.

Test repositories against PostgreSQL and validate their service aliases with the
container lint or `debug:container`.
