# Authenticated messaging writes

Run against the migrated/seeded PostgreSQL test baseline:

```text
php -d memory_limit=1G vendor/bin/phpunit -c phpunit.dist.xml --no-coverage tests/E2E/MessagingAuthenticatedWritesFlowTest.php
```

The normal bootstrap clones auth/main per run. Do not run `make test-db` while another suite
is executing, and never substitute development fixtures or SQLite.

The flow uses real login HTTP bearer tokens, creates its own organization, assigns explicit
messaging read/write permissions to a fresh user and adds that organization's member to a
new channel. It checks the exact participants and authors, root/edit/reply persistence,
root/reply collection separation, multipart PDF upload and exact downloaded bytes, delivery
and read markers/receipts, tombstone retention and API redaction, and six ordered realtime
emissions after their corresponding database rows exist.

A second flow authenticates both a same-organization nonparticipant and an owner of another
organization. Reads, posts, edits, deletes, replies, upload/download, delivery and read-marker
operations return exact 403/404 statuses and leave rows, counters and emissions unchanged.
Adding a member from another organization returns the established 422 input-validation error.

The recording realtime port proves payload/scope/persistence ordering, not hub delivery or
database commit visibility in another process. The separate web `tests/e2e/live-api` smoke
requires actual workers and Mercure. These tests complement `MessagingPresentationFlowTest`,
whose anonymous route checks cannot establish authenticated write behavior.
