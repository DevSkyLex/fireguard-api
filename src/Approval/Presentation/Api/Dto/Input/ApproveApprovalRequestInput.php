<?php

declare(strict_types=1);

namespace Approval\Presentation\Api\Dto\Input;

use ApiPlatform\Metadata\ApiProperty;
use Approval\Presentation\Api\Serialization\ApprovalSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO ApproveApprovalRequestInput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ApproveApprovalRequestInput
{
  // #region Properties
  /**
   * Property decisionNote.
   *
   * @since 1.0.0
   */
  #[Assert\Length(max: 2000)]
  #[Groups([ApprovalSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Optional free-form decision note', required: false)]
  public ?string $decisionNote = null;
  // #endregion
}
