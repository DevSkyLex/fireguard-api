<?php

declare(strict_types=1);

namespace Assistant\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Get, GetCollection, Post};
use ApiPlatform\OpenApi\Model\{Operation, Response};
use Assistant\Presentation\Api\Dto\Input\{AskAssistantQuestionInput, StartAssistantThreadInput};
use Assistant\Presentation\Api\Dto\Output\{AskAssistantQuestionOutput, AssistantThreadDetailOutput, AssistantThreadOutput, AssistantThreadSubscriptionOutput};
use Assistant\Presentation\Api\Operation\AssistantOperations;
use Assistant\Presentation\Api\Processor\{AskAssistantQuestionProcessor, StartAssistantThreadProcessor};
use Assistant\Presentation\Api\Provider\{GetAssistantThreadProvider, GetAssistantThreadSubscriptionProvider, ListAssistantThreadsProvider};
use Assistant\Presentation\Api\Serialization\AssistantSerializationGroup;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Resource AssistantThreadResource.
 *
 * Member-private AI-assistant chat threads: a member may only ever start,
 * list, read, or ask questions on their OWN threads within an organization
 * — there is no shared/organization-wide assistant thread in this design.
 * Every operation self-enforces `organization.assistant.use` in its handler.
 *
 * **Deferred org-level gate**: `settings.assistant.enabled` is NOT yet
 * checked (see `Application\Port\Outbound\Organization\AssistantOrganizationSettingsPort`
 * and `MODULE.md`'s "Deferred cross-module work") — every endpoint below
 * gates on the `organization.assistant.use` permission ONLY, for now.
 *
 * **Deviation from a flatter `/api/assistant/...` routing sketch**: this
 * module nests every route under `/organizations/{organizationId}/assistant/...`,
 * consistent with every other org-scoped resource in this API (Approval,
 * Webhook, Equipment, Inspection...) — avoiding a new, unprecedented
 * unprefixed routing convention (mirrors `Approval\Presentation\Api\Resource\ApprovalRequestResource`'s
 * documented deviation).
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'AssistantThread',
  routePrefix: '/organizations',
  description: 'Member-private AI-assistant chat threads.',
  operations: [
    new GetCollection(
      name: AssistantOperations::LIST_ASSISTANT_THREADS,
      uriTemplate: '/{organizationId}/assistant/threads',
      input: false,
      output: AssistantThreadOutput::class,
      provider: ListAssistantThreadsProvider::class,
      paginationEnabled: true,
      paginationClientItemsPerPage: true,
      paginationMaximumItemsPerPage: 100,
      paginationItemsPerPage: 30,
      normalizationContext: ['groups' => [AssistantSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Assistant'],
        summary: 'List my assistant threads',
        description: 'Lists the requesting member\'s OWN assistant threads within the organization, most recently active first. Requires organization.assistant.use.',
      ),
    ),
    new Post(
      name: AssistantOperations::START_ASSISTANT_THREAD,
      uriTemplate: '/{organizationId}/assistant/threads',
      status: HttpResponse::HTTP_CREATED,
      input: StartAssistantThreadInput::class,
      output: AssistantThreadOutput::class,
      processor: StartAssistantThreadProcessor::class,
      denormalizationContext: ['groups' => [AssistantSerializationGroup::WRITE]],
      normalizationContext: ['groups' => [AssistantSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Assistant'],
        summary: 'Start an assistant thread',
        description: 'Starts a new assistant thread for the requesting member. Requires organization.assistant.use.',
      ),
    ),
    new Get(
      name: AssistantOperations::GET_ASSISTANT_THREAD,
      uriTemplate: '/{organizationId}/assistant/threads/{threadId}',
      output: AssistantThreadDetailOutput::class,
      provider: GetAssistantThreadProvider::class,
      normalizationContext: ['groups' => [AssistantSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Assistant'],
        summary: 'Get an assistant thread',
        description: 'Returns a single assistant thread together with a page of its messages, oldest first. Requires organization.assistant.use; the thread must belong to the requesting member.',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Assistant thread retrieved'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Organization or assistant thread not found (including a thread belonging to another member)'),
        ],
      ),
    ),
    new Post(
      name: AssistantOperations::ASK_ASSISTANT_QUESTION,
      uriTemplate: '/{organizationId}/assistant/threads/{threadId}/messages',
      status: HttpResponse::HTTP_CREATED,
      input: AskAssistantQuestionInput::class,
      output: AskAssistantQuestionOutput::class,
      processor: AskAssistantQuestionProcessor::class,
      denormalizationContext: ['groups' => [AssistantSerializationGroup::WRITE]],
      normalizationContext: ['groups' => [AssistantSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Assistant'],
        summary: 'Ask a question',
        description: 'Persists the question and a placeholder pending assistant reply, then enqueues generation. Requires organization.assistant.use; the thread must belong to the requesting member. The assistant reply is returned with status "pending" until the async `assistant` Messenger transport drives it through streaming to complete/failed.',
        responses: [
          HttpResponse::HTTP_CREATED => new Response(description: 'Question persisted, reply generation enqueued'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Organization or assistant thread not found (including a thread belonging to another member)'),
          HttpResponse::HTTP_UNPROCESSABLE_ENTITY => new Response(description: 'Blank question body'),
        ],
      ),
    ),
    new Get(
      name: AssistantOperations::GET_ASSISTANT_THREAD_SUBSCRIPTION,
      uriTemplate: '/{organizationId}/assistant/threads/{threadId}/subscription',
      output: AssistantThreadSubscriptionOutput::class,
      provider: GetAssistantThreadSubscriptionProvider::class,
      normalizationContext: ['groups' => [AssistantSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Assistant'],
        summary: 'Get a Mercure subscription for an assistant thread',
        description: 'Mints a Mercure subscriber JWT scoped to ONE thread\'s private generation-stream topic, after re-running the same authorization a GET on the thread would apply. Requires organization.assistant.use; the thread must belong to the requesting member.',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Subscription token minted'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Organization or assistant thread not found (including a thread belonging to another member)'),
        ],
      ),
    ),
  ],
)]
final class AssistantThreadResource
{
}
