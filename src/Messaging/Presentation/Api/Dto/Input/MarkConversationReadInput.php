<?php

declare(strict_types=1);

namespace Messaging\Presentation\Api\Dto\Input;

/**
 * DTO MarkConversationReadInput.
 *
 * `PATCH /api/conversations/{id}/read` request body.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class MarkConversationReadInput
{
  /**
   * Property lastReadMessageId.
   *
   * Optional message IRI (or bare identifier) the member has read up to.
   * When omitted, only `lastReadAt` (now) is recorded.
   *
   * @since 1.0.0
   */
  public ?string $lastReadMessageId = null;
}
