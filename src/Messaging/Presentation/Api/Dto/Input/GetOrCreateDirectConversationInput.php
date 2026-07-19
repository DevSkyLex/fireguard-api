<?php

declare(strict_types=1);

namespace Messaging\Presentation\Api\Dto\Input;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO GetOrCreateDirectConversationInput.
 *
 * `POST /api/direct-conversations` request body (get-or-create by target
 * member, L2.4).
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class GetOrCreateDirectConversationInput
{
  /**
   * Property organization.
   *
   * The organization IRI (or bare identifier).
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank(message: 'Organization is required.')]
  public string $organization = '';

  /**
   * Property memberId.
   *
   * The other organization member's identifier to start a direct
   * conversation with. A bare identifier, mirroring
   * `AddChannelParticipantInput::$memberId` (not a member IRI).
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank(message: 'Member ID is required.')]
  #[Assert\Uuid(message: 'Member ID must be a valid UUID.')]
  public string $memberId = '';
}
