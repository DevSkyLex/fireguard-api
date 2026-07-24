<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Dto\Input\Organization;

use ApiPlatform\Metadata\ApiProperty;
use Organization\Presentation\Api\Serialization\OrganizationSerializationGroup;
use Organization\Presentation\Api\Validator\ValidApprovalPolicy\ValidApprovalPolicy;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO UpdateOrganizationApprovalInput.
 *
 * Partial organization four-eyes approval policy payload. Every field is
 * optional; only the provided fields are applied. Inside `actionRules`,
 * setting an entry to `null` removes the customization (reverting that
 * action type to "disabled").
 *
 * @category DTO
 *
 * @version 1.2.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ValidApprovalPolicy]
final class UpdateOrganizationApprovalInput
{
  // #region Properties
  /**
   * Property actionRules.
   *
   * @since 1.2.0
   *
   * @var ?array<string, ?array{enabled?: bool, minApproverRole?: string, minSeverity?: ?string}>
   */
  #[Assert\Type('array')]
  #[Groups([OrganizationSerializationGroup::WRITE])]
  #[ApiProperty(
    description: 'Per-action-type approval rules (action type value => rule, or null to revert to "disabled")',
    required: false,
    example: ['nc_waiver' => ['enabled' => true, 'minApproverRole' => 'admin', 'minSeverity' => 'critical'], 'equipment_decommission' => null],
  )]
  public ?array $actionRules = null;

  /**
   * Property allowSelfApproval.
   *
   * @since 1.2.0
   */
  #[Assert\Type('bool')]
  #[Groups([OrganizationSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Whether the requester may also decide on their own approval request', required: false)]
  public ?bool $allowSelfApproval = null;

  /**
   * Property approvalTtlDays.
   *
   * @since 1.2.0
   */
  #[Assert\Type('int')]
  #[Assert\Range(min: 1, max: 90)]
  #[Groups([OrganizationSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Days a pending approval request stays valid before it expires', required: false, example: 14)]
  public ?int $approvalTtlDays = null;
  // #endregion
}
