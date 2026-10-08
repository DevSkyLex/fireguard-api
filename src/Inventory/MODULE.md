# Inventory Module

## Overview

Inventory owns quantitative parts, consumables, warehouses, balances and their immutable movements in `main`. It does not own individual equipment in reserve: Equipment owns those assets, their catalog, quota and history. Quantities and internal values are exact decimal strings with six fractional digits. Labels and units are descriptive.

## API endpoints

All routes are prefixed by `/api/organizations/{organizationId}`.

| Route | Methods | Permission |
| --- | --- | --- |
| `/inventory-parts`, `/inventory-parts/{id}` | list/create, get/patch | inventory.read / inventory.manage |
| `/inventory-warehouses`, `/inventory-warehouses/{id}` | list/create, get/patch | inventory.read / inventory.manage |
| `/inventory-balances` | list | inventory.read |
| `/inventory-movements`, `/inventory-movements/{id}` | list, get | inventory.read |
| `/inventory-consumptions`, `/inventory-consumptions/{id}` | list/declare, get | inventory.read / inventory.consume and interventions.execute |
| `/inventory-consumptions/{id}/reconcile` | post with empty body | inventory.manage and interventions.execute |
| `/inventory-returns` | post | inventory.consume and interventions.execute |
| `/inventory-corrections` | post | inventory.manage and maintenance_cost.manage |

Permission names carry the `organization.` prefix. Collections use Hydra pagination, `page` and `itemsPerPage` (maximum 100). Parts and warehouses accept `search` and optional `archived=true|false`; absent archived includes history. Balances accept warehouseId and partId. Movements additionally accept interventionId; consumptions also accept status.

Part input is code, label, unit and kind (`part|consumable`). Warehouse input is code and name. Codes and part kinds are immutable; label/name, unit and archived are patchable. Detail reads retain archived references.

Consumption input is stable UUID clientOperationId, partId, warehouseId, positive decimal quantity, interventionId, optional workItemId/equipmentId, and an ISO occurrence with explicit offset. The output distinguishes `confirmed` and `received_pending` with reason `insufficient_stock|archived_reference|missing_balance`, a nullable movementId, late and replayed. Returns carry consumptionId, quantity and a required reason. Corrections carry signed quantity and reason; optional unitCost never silently becomes zero.

Balances and movements attach `valuation` only when the caller holds maintenance_cost.read. Its totalValue and unitCost are nullable, currency is fixed per organization, and incomplete remains true for unknown values. Ordinary quantity reads carry no internal values.

## Flows

`InventoryInterventionHistoryPort` exposes the existence of every retained
declaration or movement without quantities or values. Intervention deletion uses
its shared work fence before the parent row lock and refuses hard DELETE while
these references exist, including received_pending declarations and fully
returned consumptions. Return and reconciliation keep their operational context.

A valid physical consumption is saved before resolution in the same main transaction. It debits the complete requested quantity and its CUMP value or remains received_pending without any stock movement. No partial issue or negative balance is possible. Archived references and absent balance books also retain the declaration. Reconciliation uses the declaration's original quantity and work context.

Each operation has an organization-scoped stable identity and payload hash. A conflicting body returns 409. Replaying consumption returns its original response snapshot even if the declaration has since been reconciled; detail reads return its current status. Confirmed receipts, returns and corrections replay their immutable movement.

InventoryStockReceiptPort receives procurement quantities or reverses a linked receipt. tryReverse returns stock_insufficient without changing stock or poisoning the caller's transaction; Procurement retains the physical return and may retry. Unconfirmed supplier returns are not registered as completed inventory operations. Confirmed returns are bounded by both the original received quantity and available stock.

Returned intervention parts cannot exceed their original consumption. Every correction/return has a reason and preserves original movements. Each partial return allocates the remaining original value proportionally to the remaining original quantity, so rounding cannot credit more than the original value. The last return receives its exact residual; unknown original or prior return values remain unknown. Unknown receipt prices make a nonempty balance incomplete; only emptying that balance establishes known zero before new priced receipts.

## Architecture

Handlers inject the inventory store, main transaction manager and published Intervention/MaintenanceCost ports. The repository only queries Inventory tables. Organization authorization occurs at the HTTP boundary; Intervention validates work ownership, equipment scope and execution. Procurement and intervention costs call public Application ports/contracts.

Stock paths acquire locks in one order: intervention publication fence and parent when applicable, organization currency, stable operation identity, declaration/references, then balance. Procurement receipts and reversals enter the same currency-before-operation/reference order. Reconciliation reads its immutable parent scope before acquiring the work fence and currency, then locks the declaration. Consumption and publication share the `inventory-intervention:{organizationId}:{interventionId}` advisory fence before the parent intervention lock. Publication refuses received unresolved declarations and captures immutable facts. Late declarations and returns remain current cost facts with linked adjustments; they never mutate an existing dossier.

The public intervention resource port also supplies finance-authorized direct-equipment candidate intervention identifiers from owned movements and pending declarations. Both the equipment input and distinct intervention output are bounded to 10000 identities, with an explicit refusal of oversized scopes. No quantities, valuations or sibling persistence cross this directory projection; MaintenanceCost verifies actual current/captured allocations before selecting a financial dossier.

CUMP allocates total balance value proportionally with one final half-up rounding at six places, retaining the residual. The last issue uses the entire remaining value. Returns are linked to their source movement. Missing price is represented by null, rather than a fabricated zero.

## Configuration

`config/modules/inventory.yaml` wires every service and explicitly uses the main connection for the SQL repository and the main entity manager for its transaction manager. Pure row mapping and collection predicates do not open connections; advisory fences use the repository's existing main transaction. Doctrine maps `Inventory\\Infrastructure\\Persistence\\Doctrine\\Record` only under main. Routes register the Inventory API Resource directory. DATABASE_STORAGE_TIMEZONE controls persisted wall-clock timestamps; reads and writes convert through that zone.

Within `Infrastructure/Persistence/Doctrine`, `Query` owns the pure collection predicates, `Lock` owns transaction-scoped advisory fences, and `Mapper` restores persisted values. The repository constructs its lock collaborator with the same explicitly wired main connection. Query and lock collaborators are outside service discovery and never receive a separate default connection.

Migration `Version20261006111000` adds the scoped reference catalog, balances, immutable movement ledger, consumption declarations and operation receipts. Balances have an organization/warehouse/part unique key and a nonnegative database constraint. No auth/main joins are used.

## Testing

Retention coverage checks abandoned parents and prepared tasks with both confirmed
and received_pending declarations. Rejected DELETE preserves balances, details,
returns and reconciliation; a fully returned issue still retains its history.

Domain tests cover weighted average allocation, fractional quantities, last-issue residuals and incomplete values. Handler tests cover shortages, missing books, late facts, original-snapshot replay and exact partial returns across tiny, fractional, mixed and unknown valuations. PostgreSQL functional tests cover reconciliation, returns with tiny rounding residuals and replay, money permissions, archived history, scoped 404s, unchanged published dossiers and search/pagination parity. Two-session PostgreSQL integration tests use the real currency adapter to dispute the final part and to serialize Procurement reception against intervention consumption, retaining exact balances and replay receipts.

Use `make test-db` once to build the isolated template, then run the focused Inventory suites. Source/test PHPStan, container lint, both Deptrac configurations, OpenAPI and main schema gates are required.

## Error codes

| Code | Status | Meaning |
| --- | --- | --- |
| inventory_not_found | 404 | Unknown or foreign inventory reference/detail |
| inventory_conflict | 409 | Reused operation payload, duplicate catalog code, invalid return bound or publication blocker |
| inventory_invalid | 422 | Invalid exact quantity, monetary value, reason, reference properties or filters |

Authentication and missing contextual permission fail closed with 401/403. Outside organizations use the same 404 as unknown organizations. Publication callers receive the public InventoryPublicationBlockedException contract, rather than an Inventory Domain type.
