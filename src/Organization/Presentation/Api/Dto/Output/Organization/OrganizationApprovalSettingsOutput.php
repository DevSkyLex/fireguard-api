<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Dto\Output\Organization;

use ApiPlatform\Metadata\ApiProperty;
use Organization\Domain\Catalog\OrganizationApprovalDefaults;
use Organization\Domain\ValueObject\OrganizationApprovalSettings;
use Organization\Presentation\Api\Serialization\OrganizationSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO OrganizationApprovalSettingsOutput.
 *
 * Read representation of an organization's four-eyes approval policy.
 * `actionRules` carries only the CUSTOMIZED action types (every other
 * action type is disabled); combine with `GET /approvals/action-types` for
 * the full catalog.
 *
 * @category DTO
 *
 * @version 1.2.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationApprovalSettingsOutput
{
  // #region Properties
  /**
   * Property actionRules.
   *
   * @since 1.2.0
   *
   * @var array<string, array{enabled: bool, minApproverRole: string, minSeverity: ?string}>
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false, description: 'Customized per-action-type approval rules (action types absent here are disabled)')]
  public array $actionRules = [];

  /**
   * Property allowSelfApproval.
   *
   * @since 1.2.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false, description: 'Whether the requester may also decide on their own approval request')]
  public bool $allowSelfApproval = OrganizationApprovalDefaults::ALLOW_SELF_APPROVAL;

  /**
   * Property approvalTtlDays.
   *
   * @since 1.2.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false, description: 'Days a pending approval request stays valid before it expires')]
  public int $approvalTtlDays = OrganizationApprovalDefaults::APPROVAL_TTL_DAYS;
  // #endregion

  // #region Methods
  /**
   * Method fromDomain.
   *
   * @static
   *
   * Builds the output from the domain approval settings value object.
   *
   * @since 1.2.0
   *
   * @param OrganizationApprovalSettings $approval the domain approval settings
   *
   * @return self the output instance
   */
  public static function fromDomain(OrganizationApprovalSettings $approval): self
  {
    $output = new self();
    $output->actionRules = $approval->actionRules;
    $output->allowSelfApproval = $approval->allowSelfApproval;
    $output->approvalTtlDays = $approval->approvalTtlDays;

    return $output;
  }
  // #endregion
}
