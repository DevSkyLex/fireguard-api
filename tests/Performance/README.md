# Workload performance budgets

After `make test-db`, run `make benchmark`. This explicit PHPUnit configuration clones
the PostgreSQL test templates and removes its synthetic data through rollback or explicit
cleanup inside the clone. It never seeds development.
CI runs the same configuration as an independent blocking job.

The fixed dataset contains 1,000 interventions, 20,000 work items, 20,000 time entries,
500 members and a 30-day range, with a frozen clock. Three samples must produce identical
projection fingerprints and view hashes. Every sample must execute exactly two contribution
queries, two projection queries and eight fixed statistics queries, with zero hydrated ORM
entities. The test-only counter retains a count, without SQL or parameter values.

The median of three samples must remain below 2,000 ms for contribution reads,
15,000 ms for the complete projection, 2,000 ms for statistics and 256 MiB incremental
peak memory. These generous runner budgets reject regressions in scale while tolerating
normal CI variation; they are acceptance limits, not claims about observed latency.
`var/workload-benchmark.json` records every sample, including query counts and memory.
Change budgets only with a reviewed measurement on the same dataset and runner class.

## Maintenance and import volumes

`MaintenanceImportBoundedWorkloadTest` runs three complete maintenance sweeps over
20,000 published equipment records. The caller keeps one pending ORM entity: the
sweep must preserve it while bounding queries by pages, with at most 2,400 queries
and 64 MiB incremental peak memory per sample and a median duration below 60 seconds.
Native page transactions release PostgreSQL advisory locks between pages; this test
cleans its synthetic records explicitly inside the private cloned test database.

Import storage and resume use the actual execution/repository with 1,000, 5,000 and
10,000 synthetic report rows. Each volume allows at most `10 × rows + 20` queries,
32 MiB incremental peak memory and 180 seconds for append work, without per-row ORM
unit-of-work growth. Resume reads allow at most 10 queries and 2 seconds. The 10,000-row
case stresses storage beyond the accepted 5,000-row CSV cap; it does not represent
an accepted CSV import or end-to-end import latency.

`var/maintenance-sweep-benchmark.json` and `var/import-report-benchmark.json` record
the samples alongside the workload report. CI retains these three exact artifacts.

The local 2026-10-02 run measured a 7.568-second maintenance median, 1,300–1,302 queries,
one retained ORM entity and at most 4 MiB additional peak memory. Appending 1,000/5,000/
10,000 report rows took 8.057/41.714/85.233 seconds with 10,002/50,002/100,002 queries;
resume used ten queries and 9.1–15.0 ms at every volume. These observations describe that
runner and dataset; the acceptance budgets above remain the blocking limits.
