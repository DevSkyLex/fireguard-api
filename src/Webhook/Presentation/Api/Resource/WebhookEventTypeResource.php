<?php

declare(strict_types=1);

namespace Webhook\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, GetCollection};
use ApiPlatform\OpenApi\Model\Operation;
use Webhook\Presentation\Api\Dto\Output\WebhookEventTypeOutput;
use Webhook\Presentation\Api\Operation\WebhookOperations;
use Webhook\Presentation\Api\Provider\WebhookEventTypeProvider;
use Webhook\Presentation\Api\Serialization\WebhookSerializationGroup;

/**
 * Resource WebhookEventTypeResource.
 *
 * Reference-catalog endpoint (ARCHITECTURE.md) exposing the curated public
 * event type allowlist a subscription may register for.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'WebhookEventType',
  description: 'Reference catalog of subscribable webhook event types.',
  operations: [
    new GetCollection(
      name: WebhookOperations::LIST_WEBHOOK_EVENT_TYPES,
      uriTemplate: '/webhooks/event-types',
      input: false,
      output: WebhookEventTypeOutput::class,
      provider: WebhookEventTypeProvider::class,
      paginationEnabled: false,
      normalizationContext: ['groups' => [WebhookSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Webhooks'],
        summary: 'List webhook event types',
        description: 'Returns the curated public event type allowlist a webhook subscription may register for.',
      ),
    ),
  ],
)]
final class WebhookEventTypeResource
{
}
