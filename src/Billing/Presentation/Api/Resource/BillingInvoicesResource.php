<?php

declare(strict_types=1);

namespace Billing\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, GetCollection};
use ApiPlatform\OpenApi\Model\Operation;
use Billing\Presentation\Api\Dto\Output\InvoiceOutput;
use Billing\Presentation\Api\Operation\BillingOperations;
use Billing\Presentation\Api\Provider\GetInvoicesProvider;
use Billing\Presentation\Api\Serialization\BillingSerializationGroup;

/**
 * Resource BillingInvoicesResource.
 *
 * Organization-scoped billing history: the organization's recent Stripe invoices
 * with their hosted page and PDF links. Requires the organization.read
 * permission.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'OrganizationBillingInvoice',
  routePrefix: '/organizations',
  description: 'Organization billing invoices.',
  operations: [
    new GetCollection(
      name: BillingOperations::LIST_INVOICES,
      uriTemplate: '/{organizationId}/billing/invoices',
      input: false,
      output: InvoiceOutput::class,
      provider: GetInvoicesProvider::class,
      normalizationContext: ['groups' => [BillingSerializationGroup::READ]],
      paginationEnabled: false,
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Billing'],
        summary: 'List organization invoices',
        description: 'Returns the most recent Stripe invoices of the organization. Requires the organization.read permission.',
      ),
    ),
  ],
)]
final class BillingInvoicesResource
{
}
