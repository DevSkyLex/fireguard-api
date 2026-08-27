<?php

declare(strict_types=1);

namespace Webhook\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Delete, Get, GetCollection, Patch, Post};
use ApiPlatform\OpenApi\Model\{Operation, Response};
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Webhook\Presentation\Api\Dto\Input\{CreateWebhookSubscriptionInput, UpdateWebhookSubscriptionInput};
use Webhook\Presentation\Api\Dto\Output\{WebhookPingOutput, WebhookSecretOutput, WebhookSubscriptionOutput};
use Webhook\Presentation\Api\Operation\WebhookOperations;
use Webhook\Presentation\Api\Processor\{CreateWebhookSubscriptionProcessor, DeleteWebhookSubscriptionProcessor, PingWebhookSubscriptionProcessor, RotateWebhookSecretProcessor, UpdateWebhookSubscriptionProcessor};
use Webhook\Presentation\Api\Provider\{GetWebhookSubscriptionProvider, ListWebhookSubscriptionsProvider};
use Webhook\Presentation\Api\Serialization\WebhookSerializationGroup;

/**
 * Resource WebhookSubscriptionResource.
 *
 * Organization-scoped outbound webhook subscription management.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'WebhookSubscription',
  routePrefix: '/organizations',
  description: 'Organization-scoped outbound webhook subscription management.',
  operations: [
    new Post(
      name: WebhookOperations::CREATE_WEBHOOK_SUBSCRIPTION,
      uriTemplate: '/{organizationId}/webhooks',
      status: HttpResponse::HTTP_CREATED,
      input: CreateWebhookSubscriptionInput::class,
      output: WebhookSecretOutput::class,
      processor: CreateWebhookSubscriptionProcessor::class,
      denormalizationContext: ['groups' => [WebhookSerializationGroup::WRITE]],
      normalizationContext: ['groups' => [WebhookSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Webhooks'],
        summary: 'Create webhook subscription',
        description: 'Creates an outbound webhook subscription. Requires organization.webhooks.manage. '
          . 'The response includes the PLAINTEXT signing secret exactly once.',
        responses: [
          HttpResponse::HTTP_CREATED => new Response(description: 'Subscription created, secret revealed once'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
          HttpResponse::HTTP_UNPROCESSABLE_ENTITY => new Response(description: 'Invalid URL, unknown event type, or subscription cap reached'),
        ],
      ),
    ),
    new GetCollection(
      name: WebhookOperations::LIST_WEBHOOK_SUBSCRIPTIONS,
      uriTemplate: '/{organizationId}/webhooks',
      input: false,
      output: WebhookSubscriptionOutput::class,
      provider: ListWebhookSubscriptionsProvider::class,
      paginationEnabled: true,
      paginationClientItemsPerPage: true,
      paginationMaximumItemsPerPage: 100,
      paginationItemsPerPage: 30,
      normalizationContext: ['groups' => [WebhookSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Webhooks'],
        summary: 'List webhook subscriptions',
        description: 'Lists an organization\'s webhook subscriptions. Requires organization.webhooks.read.',
      ),
    ),
    new Get(
      name: WebhookOperations::GET_WEBHOOK_SUBSCRIPTION,
      uriTemplate: '/{organizationId}/webhooks/{webhookId}',
      output: WebhookSubscriptionOutput::class,
      provider: GetWebhookSubscriptionProvider::class,
      normalizationContext: ['groups' => [WebhookSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Webhooks'],
        summary: 'Get webhook subscription',
        description: 'Returns a single webhook subscription. Requires organization.webhooks.read.',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Subscription retrieved'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Organization or subscription not found'),
        ],
      ),
    ),
    new Patch(
      name: WebhookOperations::UPDATE_WEBHOOK_SUBSCRIPTION,
      uriTemplate: '/{organizationId}/webhooks/{webhookId}',
      read: false,
      input: UpdateWebhookSubscriptionInput::class,
      output: WebhookSubscriptionOutput::class,
      processor: UpdateWebhookSubscriptionProcessor::class,
      denormalizationContext: ['groups' => [WebhookSerializationGroup::WRITE]],
      normalizationContext: ['groups' => [WebhookSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Webhooks'],
        summary: 'Update webhook subscription',
        description: 'Partially updates a webhook subscription (url, eventTypes, isActive, description). Requires organization.webhooks.manage.',
      ),
    ),
    new Delete(
      name: WebhookOperations::DELETE_WEBHOOK_SUBSCRIPTION,
      uriTemplate: '/{organizationId}/webhooks/{webhookId}',
      read: false,
      input: false,
      output: false,
      processor: DeleteWebhookSubscriptionProcessor::class,
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Webhooks'],
        summary: 'Delete webhook subscription',
        description: 'Permanently deletes a webhook subscription and its delivery log. Requires organization.webhooks.manage.',
        responses: [
          HttpResponse::HTTP_NO_CONTENT => new Response(description: 'Subscription deleted'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Organization or subscription not found'),
        ],
      ),
    ),
    new Post(
      name: WebhookOperations::ROTATE_WEBHOOK_SECRET,
      uriTemplate: '/{organizationId}/webhooks/{webhookId}/rotate-secret',
      status: HttpResponse::HTTP_OK,
      input: false,
      output: WebhookSecretOutput::class,
      processor: RotateWebhookSecretProcessor::class,
      normalizationContext: ['groups' => [WebhookSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Webhooks'],
        summary: 'Rotate webhook signing secret',
        description: 'Generates a new signing secret, invalidating the previous one. Requires organization.webhooks.manage. '
          . 'The response includes the new PLAINTEXT secret exactly once.',
      ),
    ),
    new Post(
      name: WebhookOperations::PING_WEBHOOK_SUBSCRIPTION,
      uriTemplate: '/{organizationId}/webhooks/{webhookId}/ping',
      status: HttpResponse::HTTP_ACCEPTED,
      input: false,
      output: WebhookPingOutput::class,
      processor: PingWebhookSubscriptionProcessor::class,
      normalizationContext: ['groups' => [WebhookSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Webhooks'],
        summary: 'Send a test delivery',
        description: 'Enqueues a synthetic `webhook.ping` test delivery to this subscription. Requires organization.webhooks.manage.',
        responses: [
          HttpResponse::HTTP_ACCEPTED => new Response(description: 'Test delivery enqueued'),
        ],
      ),
    ),
  ],
)]
final class WebhookSubscriptionResource
{
}
