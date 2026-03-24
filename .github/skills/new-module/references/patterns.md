# New Module Patterns

## Mandatory wiring

- module service file under `config/modules/`
- Doctrine mapping under the correct entity manager
- messenger handler tags for commands and queries
- API Platform classes when the module is API-facing

## Directory priorities

- always start with Application, Domain, Infrastructure, Presentation
- add Contract or Inbound ports only when another module truly depends on them
- keep Domain free of framework and persistence concerns

## Minimal deliverable

- one representative command
- one representative query
- module config and doctrine mapping
- `MODULE.md`
- unit tests plus functional or integration coverage

## API-facing default

- when a module is exposed over HTTP, start with Resource, Operation, Processor or Provider, Input DTO, and Output DTO together
- keep route-level security coarse and enforce business scope in handlers behind the API layer
