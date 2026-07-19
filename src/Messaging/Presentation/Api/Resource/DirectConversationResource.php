<?php

declare(strict_types=1);

namespace Messaging\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Post};
use Messaging\Presentation\Api\Dto\Input\GetOrCreateDirectConversationInput;
use Messaging\Presentation\Api\Dto\Output\ConversationOutput;
use Messaging\Presentation\Api\Processor\Conversation\GetOrCreateDirectConversationProcessor;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resource DirectConversationResource.
 *
 * 1-to-1 private conversations between two organization members (L2.4) — a
 * `Conversation` with `subjectType=DIRECT`, `visibility=PARTICIPANTS`, and
 * `subjectId` set to the deterministic, order-independent pair key derived
 * by `Messaging\Domain\Service\DirectConversationKey`. Mirrors
 * `ChannelResource`: a dedicated top-level creation endpoint, while
 * messages/read/subscription/favorite reuse the EXISTING
 * `/api/conversations/{id}/...` endpoints unchanged — a direct conversation
 * id IS a conversation id. It deliberately does NOT appear in
 * `GET /api/conversations` (see `MessagingConversationRepository::list()`).
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'DirectConversation',
  operations: [
    new Post(
      uriTemplate: '/direct-conversations',
      input: GetOrCreateDirectConversationInput::class,
      output: ConversationOutput::class,
      processor: GetOrCreateDirectConversationProcessor::class,
      status: Response::HTTP_OK,
      security: "is_granted('ROLE_USER')",
    ),
  ],
)]
final class DirectConversationResource
{
}
