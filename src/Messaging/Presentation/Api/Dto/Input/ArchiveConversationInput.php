<?php

declare(strict_types=1);

namespace Messaging\Presentation\Api\Dto\Input;

/**
 * DTO ArchiveConversationInput.
 *
 * `PATCH /api/conversations/{id}` request body.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ArchiveConversationInput
{
  /**
   * Property isArchived.
   *
   * @since 1.0.0
   */
  public bool $isArchived = true;
}
