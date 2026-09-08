<?php

declare(strict_types=1);

namespace User\Presentation\Api\Dto\Output\EmailChange;

use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Serializer\Attribute\{Groups, SerializedName};
use User\Presentation\Api\Serialization\UserSerializationGroup;

/**
 * DTO ConfirmEmailChangeOutput.
 *
 * @category Output DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ConfirmEmailChangeOutput
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @param bool $success whether the change was applied
   * @param string $message informational message
   */
  public function __construct(
    #[Groups(groups: [UserSerializationGroup::EMAIL_CHANGE_READ])]
    #[SerializedName(serializedName: 'success')]
    #[ApiProperty(
      description: 'Whether the email address was changed successfully',
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
      example: 'Your email address has been changed. Please sign in again with the new address.',
    )]
    public string $message,
  ) {
  }
  // #endregion
}
