# Service wiring review

This review is read-only. Read `.codex/rules/module-config.md`, the owning module contracts
and [dual-database ownership](../../fg-api-migrate/references/dual-database.md).

Trace each changed constructor dependency to its service definition or Application port alias.
Check import/exclude order, autoconfiguration, visibility, decorators, tags, handler routing
and duplicate registrations. Resolve scalar configuration against documented parameter names
without reading secret values.

For every Doctrine consumer, identify the Record mapping, explicit entity manager and
transaction manager. A business service wired to the auth default can pass static analysis.
For Messenger/Scheduler, inspect bus/transport selection and handler tags instead of assuming
that a class name proves registration.

Review provided `make lint` or `debug:container` output if available. Ask the parent to run
container commands when fresh evidence is needed; booting Symfony can write cache and is not
a read-only action. Distinguish a proven alias mismatch from a runtime check still missing.
