# Procurement

## Overview

Procurement owns organization-internal suppliers, purchase orders, physical receipts and motivated supply returns. It supports maintenance resources; commercial quotations, taxes, customer invoicing and payments remain in the ERP. Billing continues to own the FireGuard SaaS subscription.

Part and consumable quantities are exact decimal strings with six fractional digits. Equipment quantities must be whole units. Unit costs are nonnegative exact strings or explicitly unknown; no floating point amount is accepted. The organization currency is resolved and locked through MaintenanceCost.

A part receipt creates quantitative inventory. An equipment receipt instead remains awaiting individualization, without creating a quantitative stock row. Individualization creates reserve assets through Equipment, preserving its catalogue, permissions, identity constraints and quota checks.

ProcurementEconomicOverviewPort exposes private purchase economics to a reporting caller that has already verified organization scope and financial read permission. Its inclusive-start, exclusive-end period selects at most 500 non-draft orders by createdAt; receipt and return values include the entire physical history of those selected orders, including receipts after the reporting window. A single main-database statement preserves a consistent view. Ordered value means the effective commitment after cancellation, received value is gross delivery, outstanding value excludes cancelled remainder and is never reopened by returns, and returned value retains physical returns even while stock reconciliation is pending. Unknown prices produce a known subtotal and an explicitly incomplete total. Oversized selections or currencies differing from the configured organization currency are refused; no supplier contacts enter the report.

## API endpoints

All routes are under `/api/organizations/{organizationId}/procurement` and require authentication.

| Method | Path | Behavior |
|---|---|---|
| GET / POST | `/suppliers` | List or create internal suppliers and contacts. |
| GET / PATCH | `/suppliers/{id}` | Read or change a supplier. |
| POST | `/suppliers/{id}/archive` | Archive without deleting current purchases. |
| GET / POST | `/orders` | List or prepare purchase drafts. |
| GET / PATCH | `/orders/{id}` | Read or edit a draft. |
| POST | `/orders/{id}/order` | Freeze ordered lines for delivery. |
| POST | `/orders/{id}/cancel-remaining` | Cancel only quantities not yet received. |
| GET / POST | `/orders/{id}/receipts` | List receipts or record a physical delivery. |
| GET | `/receipts/{id}` | Read retained delivery evidence. |
| POST | `/receipts/{id}/individualize` | Create a bounded reserve-equipment batch. |
| POST | `/receipts/{id}/returns` | Retain a motivated physical return and attempt stock confirmation. |
| GET | `/receipts/{id}/returns` | List retained returns, including those awaiting reconciliation. |
| GET | `/returns/{id}` | Read a physical return and its reconciliation status. |
| POST | `/returns/{id}/reconcile` | Explicitly retry stock confirmation. |

Collections use Hydra pagination, default 30 and maximum 100. Supplier filters include search and archived: omission or false selects active suppliers, true selects archived suppliers, and explicit all selects both with the same scoped count and pagination. Order filters include status and supplierId. All resource references are resolved in the same organization.

Mutations of an existing resource require `If-Match: "revision-N"`. Receipt creation uses the order revision; individualization and return declaration use the receipt revision; return reconciliation uses the return revision. A matching saved clientOperationId replays before the stale revision check. UUID representations are normalized to lowercase.

## Flows

A draft becomes ordered, then partial_received or received. Ordered lines cannot be edited. Cancelling remaining quantities preserves all receipts and returns. Supplier archival prevents new selections while already ordered purchases can still be received.

Part code, label and unit are captured through one scoped catalog lookup during draft preparation. The ordered line keeps this identity when catalog labels or units later change; these nonfinancial fields remain visible to a quantity receiver without inventory or cost read permissions.

Each physical delivery names a stable line UUID, exact quantity, receivedAt with an explicit offset, and clientOperationId. Organization locking, remaining-quantity reservation, durable receipt, inventory confirmation and the event outbox share the main transaction. Replaying a declaration does not create another movement or asset. Reusing its operation UUID for another declaration is a conflict.

Equipment deliveries contain at most 100 units per receipt. A quota failure or an unavailable type keeps the physical receipt awaiting_individualization, without partial equipment creation. Every attempt is saved, including blocked attempts; another attempt requires a new operation UUID. A transport retry retains the original UUID. Individualized receipts preserve their equipment IDs and are excluded from quantitative piece stock.

A physical supply return preserves its quantity and reason even when available computer stock cannot confirm the debit. returnedQuantity counts the physical fact; pendingReturnQuantity separately counts stock awaiting reconciliation. A typed blocked inventory result creates no debit, negative balance or partial movement. Explicit reconciliation retries the original inventory operation key and, when successful, updates the stock movement, pending quantity, return status and outbox atomically. Returns do not reopen the order's remaining delivery quantity. Equipment already individualized is handled through its park lifecycle.

## Architecture

Presentation translates DTOs into CommandBusPort and QueryBusPort messages. ManageProcurementHandler and ReadProcurementHandler are the sole mutation and read entry points. Domain models enforce order lifecycle, exact quantities, line separation, contact validity and optimistic revisions.

ProcurementRepository uses an explicitly named main DBAL connection. It scopes every lookup and serializes mutations with a transaction-scoped organization advisory lock. Physical receipt and return identity fields are immutable on repository updates. Stable operation declarations and motivated return evidence are retained.

Cross-module access uses published Application contracts and ports: InventoryStockReceiptPort, InventoryPartDirectoryPort, EquipmentReserveReceiptPort, MaintenanceCurrencyPort and OrganizationAuthorizationPort. The module never reads sibling persistence records during production execution.

## Configuration

`config/modules/procurement.yaml` binds the repository to main and registers providers, processors, the exception subscriber and message handlers. Events use MainTransactionEventDispatcher.

The module requires `organization.procurement.read/manage`. Part receipts, supply returns and reconciliation additionally require `organization.inventory.manage`; individualization requires `organization.equipment.write`. Cost visibility independently requires `organization.maintenance_cost.read`; explicitly writing or clearing a unitCost requires `organization.maintenance_cost.manage`. Hidden prices are omitted from projections and DTOs; an authorized unknown cost remains null.

Additive main migrations 20261006112000–20261006112002 add suppliers, orders, physical receipts, immutable operation keys, returns and scoped indexes. UTC timestamps carry datetime_immutable type comments. Decimal columns use NUMERIC(24,6).

## Testing

Domain tests cover supplier contacts/archive, order transitions, fractional consumables, integer equipment, bounded receipts and returns. Handler and transport tests cover authority, isolated financial visibility, exact decimals, optimistic revision, operation replay and UUID aliases.

PostgreSQL integration tests use independent connections for organization locks, the last quantity, retained declarations, transaction rollback and outbox visibility. Functional API tests exercise real inventory receipt/return bridges and real quota-backed equipment individualization, including partial delivery, archived suppliers, hidden costs, replay, cancellation and organization denial.

Run targeted Procurement tests, PHPStan, scoped formatting, architecture checks and container lint. Root API gates additionally validate migrations, Doctrine schemas and OpenAPI freshness.

## Error codes

- 400: malformed transport.
- 403: active organization member missing an applicable procurement, stock, equipment or financial permission.
- 404: organization outside scope or a resource/reference outside the authorized organization.
- 409: invalid lifecycle, quantity exceeding retained bounds, or an operation UUID reused for a different declaration.
- 412: stale or malformed resource revision.
- 422: invalid exact amount, quantity, contact, line kind, template or timestamp.
- 428: missing If-Match on a new mutation of an existing resource.

Blocked quota individualization and computer-stock shortage are accepted physical records with a visible pending status, not partially confirmed stock or equipment.
