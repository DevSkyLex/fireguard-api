<?php

declare(strict_types=1);

namespace Procurement\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Get, GetCollection, Patch, Post, QueryParameter};
use ApiPlatform\OpenApi\Model\Operation;
use Procurement\Presentation\Api\Dto\Input\{ChangePurchaseOrderInput, ChangeSupplierInput, IndividualizeReceiptInput, ReceivePurchaseOrderInput, ReturnProcurementReceiptInput};
use Procurement\Presentation\Api\Dto\Output\{ProcurementReceiptOutput, ProcurementReturnOutput, PurchaseOrderOutput, SupplierOutput};
use Procurement\Presentation\Api\Operation\ProcurementOperations;
use Procurement\Presentation\Api\Processor\ProcurementProcessor;
use Procurement\Presentation\Api\Provider\ProcurementProvider;

/** Exact and idempotent internal procurement; commercial billing remains in the ERP. */
#[ApiResource(
  shortName: 'Procurement',
  security: "is_granted('ROLE_USER')",
  normalizationContext: ['groups' => ['procurement:read'], 'skip_null_values' => false],
  denormalizationContext: ['groups' => ['procurement:write']],
  operations: [
    new GetCollection(name: ProcurementOperations::RETURNS, uriTemplate: '/organizations/{organizationId}/procurement/receipts/{id}/returns', output: ProcurementReturnOutput::class, provider: ProcurementProvider::class, paginationEnabled: true, paginationClientItemsPerPage: true, paginationItemsPerPage: 30, paginationMaximumItemsPerPage: 100, openapi: new Operation(tags: ['Procurement'], summary: 'List retained physical supplier returns')),
    new Get(name: ProcurementOperations::RETURN_DETAIL, uriTemplate: '/organizations/{organizationId}/procurement/returns/{id}', output: ProcurementReturnOutput::class, provider: ProcurementProvider::class, openapi: new Operation(tags: ['Procurement'], summary: 'Read a retained physical return')),
    new Post(name: ProcurementOperations::RECONCILE_RETURN, uriTemplate: '/organizations/{organizationId}/procurement/returns/{id}/reconcile', output: ProcurementReturnOutput::class, input: IndividualizeReceiptInput::class, read: false, status: 200, processor: ProcurementProcessor::class, openapi: new Operation(tags: ['Procurement'], summary: 'Explicitly reconcile a physical supply return')),
    new GetCollection(name: ProcurementOperations::SUPPLIERS, uriTemplate: '/organizations/{organizationId}/procurement/suppliers', output: SupplierOutput::class, provider: ProcurementProvider::class, paginationEnabled: true, paginationClientItemsPerPage: true, paginationItemsPerPage: 30, paginationMaximumItemsPerPage: 100, parameters: ['search' => new QueryParameter(schema: ['type' => 'string'], castToArray: false), 'archived' => new QueryParameter(description: 'false or omitted selects active suppliers; true selects archived suppliers; all selects both.', schema: ['type' => 'string', 'enum' => ['false', 'true', 'all']], castToArray: false, castToNativeType: false)], openapi: new Operation(tags: ['Procurement'], summary: 'List suppliers')),
    new Post(name: ProcurementOperations::CREATE_SUPPLIER, uriTemplate: '/organizations/{organizationId}/procurement/suppliers', output: SupplierOutput::class, processor: ProcurementProcessor::class, input: ChangeSupplierInput::class, read: false, status: 201, openapi: new Operation(tags: ['Procurement'], summary: 'Create an internal supplier')),
    new Get(name: ProcurementOperations::SUPPLIER, uriTemplate: '/organizations/{organizationId}/procurement/suppliers/{id}', output: SupplierOutput::class, provider: ProcurementProvider::class, openapi: new Operation(tags: ['Procurement'], summary: 'Read a supplier')),
    new Patch(name: ProcurementOperations::CHANGE_SUPPLIER, uriTemplate: '/organizations/{organizationId}/procurement/suppliers/{id}', output: SupplierOutput::class, processor: ProcurementProcessor::class, input: ChangeSupplierInput::class, read: false, status: 200, openapi: new Operation(tags: ['Procurement'], summary: 'Change a supplier')),
    new Post(name: ProcurementOperations::ARCHIVE_SUPPLIER, uriTemplate: '/organizations/{organizationId}/procurement/suppliers/{id}/archive', output: SupplierOutput::class, processor: ProcurementProcessor::class, input: false, read: false, status: 200, openapi: new Operation(tags: ['Procurement'], summary: 'Archive a supplier retaining current purchases')),
    new GetCollection(name: ProcurementOperations::ORDERS, uriTemplate: '/organizations/{organizationId}/procurement/orders', output: PurchaseOrderOutput::class, provider: ProcurementProvider::class, paginationEnabled: true, paginationClientItemsPerPage: true, paginationItemsPerPage: 30, paginationMaximumItemsPerPage: 100, parameters: ['status' => new QueryParameter(schema: ['type' => 'string', 'enum' => ['draft', 'ordered', 'partial_received', 'received', 'cancelled']], castToArray: false), 'supplierId' => new QueryParameter(schema: ['type' => 'string', 'format' => 'uuid'], castToArray: false)], openapi: new Operation(tags: ['Procurement'], summary: 'List purchase orders')),
    new Post(name: ProcurementOperations::CREATE_ORDER, uriTemplate: '/organizations/{organizationId}/procurement/orders', output: PurchaseOrderOutput::class, processor: ProcurementProcessor::class, input: ChangePurchaseOrderInput::class, read: false, status: 201, openapi: new Operation(tags: ['Procurement'], summary: 'Create a purchase draft')),
    new Get(name: ProcurementOperations::ORDER, uriTemplate: '/organizations/{organizationId}/procurement/orders/{id}', output: PurchaseOrderOutput::class, provider: ProcurementProvider::class, openapi: new Operation(tags: ['Procurement'], summary: 'Read a purchase order')),
    new Patch(name: ProcurementOperations::CHANGE_ORDER, uriTemplate: '/organizations/{organizationId}/procurement/orders/{id}', output: PurchaseOrderOutput::class, processor: ProcurementProcessor::class, input: ChangePurchaseOrderInput::class, read: false, status: 200, openapi: new Operation(tags: ['Procurement'], summary: 'Edit a purchase draft')),
    new Post(name: ProcurementOperations::PLACE_ORDER, uriTemplate: '/organizations/{organizationId}/procurement/orders/{id}/order', output: PurchaseOrderOutput::class, processor: ProcurementProcessor::class, input: false, read: false, status: 200, openapi: new Operation(tags: ['Procurement'], summary: 'Place a purchase order')),
    new Post(name: ProcurementOperations::CANCEL_REMAINING, uriTemplate: '/organizations/{organizationId}/procurement/orders/{id}/cancel-remaining', output: PurchaseOrderOutput::class, processor: ProcurementProcessor::class, input: false, read: false, status: 200, openapi: new Operation(tags: ['Procurement'], summary: 'Cancel only quantities not yet received')),
    new GetCollection(name: ProcurementOperations::RECEIPTS, uriTemplate: '/organizations/{organizationId}/procurement/orders/{id}/receipts', output: ProcurementReceiptOutput::class, provider: ProcurementProvider::class, paginationEnabled: true, paginationClientItemsPerPage: true, paginationItemsPerPage: 30, paginationMaximumItemsPerPage: 100, openapi: new Operation(tags: ['Procurement'], summary: 'List retained physical receipts')),
    new Post(name: ProcurementOperations::RECEIVE, uriTemplate: '/organizations/{organizationId}/procurement/orders/{id}/receipts', output: ProcurementReceiptOutput::class, processor: ProcurementProcessor::class, input: ReceivePurchaseOrderInput::class, read: false, status: 201, openapi: new Operation(tags: ['Procurement'], summary: 'Atomically record physical and stock receipt')),
    new Get(name: ProcurementOperations::RECEIPT, uriTemplate: '/organizations/{organizationId}/procurement/receipts/{id}', output: ProcurementReceiptOutput::class, provider: ProcurementProvider::class, openapi: new Operation(tags: ['Procurement'], summary: 'Read a physical receipt')),
    new Post(name: ProcurementOperations::INDIVIDUALIZE, uriTemplate: '/organizations/{organizationId}/procurement/receipts/{id}/individualize', output: ProcurementReceiptOutput::class, processor: ProcurementProcessor::class, input: IndividualizeReceiptInput::class, read: false, status: 200, openapi: new Operation(tags: ['Procurement'], summary: 'Create reserve equipment for received units')),
    new Post(name: ProcurementOperations::RETURN, uriTemplate: '/organizations/{organizationId}/procurement/receipts/{id}/returns', output: ProcurementReceiptOutput::class, processor: ProcurementProcessor::class, input: ReturnProcurementReceiptInput::class, read: false, status: 200, openapi: new Operation(tags: ['Procurement'], summary: 'Record a motivated supply return')),
  ],
)]
final class ProcurementResource
{
}
