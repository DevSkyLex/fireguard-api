# Stateful GET operation inventory

The full FireGuard composition declares 17 GET operations with an `Idempotency-Key` header. These reads also synchronize persisted state or produce an audited document. Each operation has an owner descriptor, one primary command handler and a fresh replay policy. The header identifies one logical execution; retries preserve the original key and input.

| Module | Operation name | Database | Persisted effect |
| --- | --- | --- | --- |
| Equipment | `equipment_export` | main | CSV export audit |
| Equipment | `equipment_export_report` | main | PDF export audit |
| Equipment | `equipment_export_labels` | main | Label export audit |
| Facility | `facility_export` | main | CSV export audit |
| Audit | `audit_events_export` | auth | CSV export audit |
| Compliance | `exportOrganizationSafetyRegister` | main | Organization PDF export audit |
| Compliance | `exportFacilitySafetyRegister` | main | Facility PDF export audit |
| Inspection | `inspection_export` | main | CSV export audit |
| Inspection | `inspection_export_non_conformities` | main | Non-conformity CSV export audit |
| Inspection | `inspection_export_report` | main | Inspection PDF export audit |
| Inspection | `inspection_export_non_conformities_report` | main | Non-conformity PDF export audit |
| Intervention | `intervention_export` | main | CSV export audit |
| Intervention | `intervention_export_report` | main | PDF export audit |
| Maintenance | `maintenance_schedule_export` | main | Schedule CSV export audit |
| Organization | `listOrganizationInvitations` | main | Invitation state synchronization |
| Onboarding | `getOrganizationOnboarding` | main | Onboarding session synchronization |
| Calendar | `getCalendarFeedIcs` | main | Feed usage timestamp |

The two live Compliance PDF exports return HTTP 200 with an `application/pdf` binary body. Their OpenAPI metadata publishes that existing response, including the mandatory header. Stored immutable snapshot downloads are ordinary reads and are outside this inventory.

The calendar feed URL credential authenticates the feed. Its `Idempotency-Key` identifies an execution independently of that credential. The rendered ICS document uses the ordinary result replay window because its body contains no feed credential.

[HttpIdempotencyCompositionTest](../../tests/Architecture/Unit/HttpIdempotencyCompositionTest.php) compares this independently maintained operation list with every GET in the selected owner manifests and real API metadata. It also checks header schemas and replay policies for all declared operations, and generates OpenAPI to verify the two Compliance PDF responses. Adding a stateful GET requires updating its owner manifest, this inventory and that test.
