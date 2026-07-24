<?php

declare(strict_types=1);

namespace Approval\Presentation\Api\Dto\Output;

use ApiPlatform\Metadata\ApiProperty;
use Approval\Presentation\Api\Serialization\ApprovalSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO ApprovalRequestOutput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ApprovalRequestOutput
{
  // #region Properties
  /**
   * Property id.
   *
   * @since 1.0.0
   */
  #[Groups([ApprovalSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false, identifier: true)]
  public string $id = '';

  /**
   * Property organizationId.
   *
   * @since 1.0.0
   */
  #[Groups([ApprovalSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $organizationId = '';

  /**
   * Property actionType.
   *
   * @since 1.0.0
   */
  #[Groups([ApprovalSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $actionType = '';

  /**
   * Property subjectId.
   *
   * @since 1.0.0
   */
  #[Groups([ApprovalSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $subjectId = '';

  /**
   * Property status.
   *
   * @since 1.0.0
   */
  #[Groups([ApprovalSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $status = '';

  /**
   * Property requestedByMemberId.
   *
   * @since 1.0.0
   */
  #[Groups([ApprovalSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $requestedByMemberId = '';

  /**
   * Property requestedByUserId.
   *
   * @since 1.0.0
   */
  #[Groups([ApprovalSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $requestedByUserId = '';

  /**
   * Property decisionByMemberId.
   *
   * @since 1.0.0
   */
  #[Groups([ApprovalSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $decisionByMemberId = null;

  /**
   * Property decisionByUserId.
   *
   * @since 1.0.0
   */
  #[Groups([ApprovalSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $decisionByUserId = null;

  /**
   * Property decisionNote.
   *
   * @since 1.0.0
   */
  #[Groups([ApprovalSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $decisionNote = null;

  /**
   * Property expiresAt.
   *
   * @since 1.0.0
   */
  #[Groups([ApprovalSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $expiresAt = '';

  /**
   * Property createdAt.
   *
   * @since 1.0.0
   */
  #[Groups([ApprovalSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $createdAt = '';

  /**
   * Property updatedAt.
   *
   * @since 1.0.0
   */
  #[Groups([ApprovalSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $updatedAt = '';

  /**
   * Property decidedAt.
   *
   * @since 1.0.0
   */
  #[Groups([ApprovalSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $decidedAt = null;

  /**
   * Property executedAt.
   *
   * @since 1.0.0
   */
  #[Groups([ApprovalSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $executedAt = null;

  /**
   * Property executionError.
   *
   * @since 1.0.0
   */
  #[Groups([ApprovalSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $executionError = null;
  // #endregion
}
