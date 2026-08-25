<?php

declare(strict_types=1);

namespace Webhook\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, GetCollection, Post};
use ApiPlatform\OpenApi\Model\{Operation, Parameter, Response};
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Webhook\Presentation\Api\Dto\Output\{WebhookDeliveryOutput, WebhookPingOutput};
use Webhook\Presentation\Api\Operation\WebhookOperations;
use Webhook\Presentation\Api\Processor\RedeliverWebhookProcessor;
use Webhook\Presentation\Api\Provider\ListWebhookDeliveriesProvider;
use Webhook\Presentation\Api\Serialization\WebhookSerializationGroup;

/**
 * Resource WebhookDeliveryResource.
 *
 * Read-only delivery log for a webhook subscription, plus a manual
 * redelivery action.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'WebhookDelivery',
  routePrefix: '/organizations',
  description: 'Outbound webhook delivery log.',
  operations: [
    new GetCollection(
      name: WebhookOperations::LIST_WEBHOOK_DELIVERIES,
      uriTemplate: '/{organizationId}/webhooks/{webhookId}/deliveries',
      input: false,
      output: WebhookDeliveryOutput::class,
      provider: ListWebhookDeliveriesProvider::class,
      paginationEnabled: true,
      paginationClientItemsPerPage: true,
      paginationMaximumItemsPerPage: 100,
      paginationItemsPerPage: 30,
      normalizationContext: ['groups' => [WebhookSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Webhooks'],
        summary: 'List webhook deliveries',
        description: 'Lists a subscription\'s delivery log. Requires organization.webhooks.read.',
        parameters: [
          new Parameter(name: 'status', in: 'query', description: 'Delivery status filter (pending|delivered|failed).', required: false, schema: ['type' => 'string']),
        ],
      ),
    ),
    new Post(
      name: WebhookOperations::REDELIVER_WEBHOOK,
      uriTemplate: '/{organizationId}/webhooks/{webhookId}/deliveries/{deliveryId}/redeliver',
      status: HttpResponse::HTTP_ACCEPTED,
      input: false,
      output: WebhookPingOutput::class,
      processor: RedeliverWebhookProcessor::class,
      normalizationContext: ['groups' => [WebhookSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Webhooks'],
        summary: 'Redeliver a webhook delivery',
        description: 'Re-enqueues a delivery (any status, including terminally failed) for another attempt. Requires organization.webhooks.manage.',
        responses: [
          HttpResponse::HTTP_ACCEPTED => new Response(description: 'Redelivery enqueued'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Organization, subscription, or delivery not found'),
        ],
      ),
    ),
  ],
)]
final class WebhookDeliveryResource
{
}
