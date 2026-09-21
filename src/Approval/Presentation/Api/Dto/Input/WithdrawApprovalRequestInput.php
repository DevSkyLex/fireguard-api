<?php

declare(strict_types=1);

namespace Approval\Presentation\Api\Dto\Input;

use ApiPlatform\Metadata\ApiProperty;
use Approval\Presentation\Api\Serialization\ApprovalSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO WithdrawApprovalRequestInput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class WithdrawApprovalRequestInput
{
  // #region Properties
  /**
   * Property decisionNote.
   *
   * @since 1.0.0
   */
  #[Assert\Length(max: 2000)]
  #[Groups([ApprovalSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Optional withdrawal reason', required: false)]
  public ?string $decisionNote = null;
  // #endregion
}
