<?php

declare(strict_types=1);

namespace User\Presentation\Api\Dto\Output\EmailChange;

use ApiPlatform\Metadata\ApiProperty;
use DateTimeImmutable;
use Symfony\Component\Serializer\Attribute\{Groups, SerializedName};
use User\Presentation\Api\Serialization\UserSerializationGroup;

/**
 * DTO RequestEmailChangeOutput.
 *
 * @category Output DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RequestEmailChangeOutput
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @param bool $success whether the request was accepted
   * @param string $message informational message
   * @param DateTimeImmutable|null $expiresAt when the confirmation token expires
   */
  public function __construct(
    #[Groups(groups: [UserSerializationGroup::EMAIL_CHANGE_READ])]
    #[SerializedName(serializedName: 'success')]
    #[ApiProperty(
      description: 'Whether the email change request was accepted',
      readable: true,
      writable: false,
      identifier: false,
      example: true,
    )]
    public bool $success,
    #[Groups(groups: [UserSerializationGroup::EMAIL_CHANGE_READ])]
    #[SerializedName(serializedName: 'message')]
    #[ApiProperty(
      description: 'Informational message',
      readable: true,
      writable: false,
      identifier: false,
      example: 'A confirmation link has been sent to the new email address.',
    )]
    public string $message,
    #[Groups(groups: [UserSerializationGroup::EMAIL_CHANGE_READ])]
    #[SerializedName(serializedName: 'expiresAt')]
    #[ApiProperty(
      description: 'When the confirmation token expires',
      readable: true,
      writable: false,
      identifier: false,
      example: '2026-08-28T11:00:00+00:00',
    )]
    public ?DateTimeImmutable $expiresAt = null,
  ) {
  }
  // #endregion
}
