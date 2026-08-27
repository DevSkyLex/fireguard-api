<?php

declare(strict_types=1);

namespace Messaging\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Delete, Get, GetCollection, Patch, Post};
use ApiPlatform\OpenApi\Model\{Operation, Parameter};
use Messaging\Presentation\Api\Dto\Input\Channel\{AddChannelParticipantInput, BindChannelTeamInput, CreateChannelInput, SetChannelParentInput, UpdateChannelInput};
use Messaging\Presentation\Api\Dto\Output\{ChannelOutput, ChannelParticipantOutput};
use Messaging\Presentation\Api\Processor\Channel\{AddChannelParticipantProcessor, BindChannelTeamProcessor, CreateChannelProcessor, DeleteChannelProcessor, RemoveChannelParticipantProcessor, SetChannelParentProcessor, UpdateChannelProcessor};
use Messaging\Presentation\Api\Provider\Channel\{GetChannelProvider, ListChannelParticipantsProvider, ListChannelsProvider};
use Symfony\Component\HttpFoundation\Response;

/**
 * Resource ChannelResource.
 *
 * Named group channels — a `Conversation` with `subjectType=CHANNEL`,
 * `subjectId=null`, `visibility=PARTICIPANTS`. Every operation requires
 * `ROLE_USER` at the resource level; the finer-grained
 * `organization.messaging.{read,write,manage}` + participant-membership
 * checks are enforced in the application layer (mirrors
 * `ConversationResource`). Messages/subscription/read/edit/delete reuse the
 * EXISTING `/api/conversations/{id}/...` endpoints unchanged — a channel id
 * IS a conversation id. `PATCH /channels/{id}/parent` (L2.6) sets or clears
 * a channel's parent, nesting it under another channel — see
 * `MessagingChannelHierarchyGuard` for the cycle/cross-organization/
 * max-depth invariants enforced before the write.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'Channel',
  operations: [
    new Post(
      uriTemplate: '/channels',
      input: CreateChannelInput::class,
      output: ChannelOutput::class,
      processor: CreateChannelProcessor::class,
      status: Response::HTTP_CREATED,
      security: "is_granted('ROLE_USER')",
    ),
    new GetCollection(
      uriTemplate: '/channels',
      output: ChannelOutput::class,
      provider: ListChannelsProvider::class,
      paginationEnabled: true,
      paginationClientItemsPerPage: true,
      paginationMaximumItemsPerPage: 100,
      paginationItemsPerPage: 30,
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(parameters: [
        new Parameter(name: 'organization', in: 'query', description: 'Organization IRI.', required: true, schema: ['type' => 'string']),
        new Parameter(name: 'isArchived', in: 'query', description: 'Archived-flag filter.', required: false, schema: ['type' => 'boolean']),
      ]),
    ),
    new Get(
      uriTemplate: '/channels/{id}',
      output: ChannelOutput::class,
      provider: GetChannelProvider::class,
      security: "is_granted('ROLE_USER')",
    ),
    new Patch(
      uriTemplate: '/channels/{id}',
      input: UpdateChannelInput::class,
      output: ChannelOutput::class,
      processor: UpdateChannelProcessor::class,
      security: "is_granted('ROLE_USER')",
    ),
    new Delete(
      uriTemplate: '/channels/{id}',
      output: false,
      processor: DeleteChannelProcessor::class,
      status: Response::HTTP_NO_CONTENT,
      security: "is_granted('ROLE_USER')",
    ),
    new Post(
      name: 'messaging_channel_add_participant',
      uriTemplate: '/channels/{id}/participants',
      input: AddChannelParticipantInput::class,
      output: ChannelParticipantOutput::class,
      processor: AddChannelParticipantProcessor::class,
      status: Response::HTTP_CREATED,
      security: "is_granted('ROLE_USER')",
    ),
    new GetCollection(
      name: 'messaging_channel_list_participants',
      uriTemplate: '/channels/{id}/participants',
      output: ChannelParticipantOutput::class,
      provider: ListChannelParticipantsProvider::class,
      paginationEnabled: false,
      security: "is_granted('ROLE_USER')",
    ),
    new Delete(
      name: 'messaging_channel_remove_participant',
      uriTemplate: '/channels/{id}/participants/{memberId}',
      output: false,
      processor: RemoveChannelParticipantProcessor::class,
      status: Response::HTTP_NO_CONTENT,
      security: "is_granted('ROLE_USER')",
    ),
    new Patch(
      name: 'messaging_channel_bind_team',
      uriTemplate: '/channels/{id}/team',
      input: BindChannelTeamInput::class,
      output: ChannelOutput::class,
      processor: BindChannelTeamProcessor::class,
      security: "is_granted('ROLE_USER')",
    ),
    new Patch(
      name: 'messaging_channel_set_parent',
      uriTemplate: '/channels/{id}/parent',
      input: SetChannelParentInput::class,
      output: ChannelOutput::class,
      processor: SetChannelParentProcessor::class,
      security: "is_granted('ROLE_USER')",
    ),
  ],
)]
final class ChannelResource
{
}
