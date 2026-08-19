<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Dto\Output\Organization;

use ApiPlatform\Metadata\ApiProperty;
use Organization\Presentation\Api\Serialization\OrganizationSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO OrganizationNavigationCountersOutput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationNavigationCountersOutput
{
  // #region Properties
  /**
   * Property openInterventions.
   *
   * The number of open field interventions for the organization (excludes
   * the `published`/`abandoned` end states). Zero when the caller lacks
   * `organization.interventions.read`.
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public int $openInterventions = 0;

  /**
   * Property openNonConformities.
   *
   * The number of non-conformities currently `open` or `in_progress` for
   * the organization. Zero when the caller lacks
   * `organization.inspection.read`.
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public int $openNonConformities = 0;

  /**
   * Property submittedInterventions.
   *
   * The number of interventions awaiting review (status `submitted`). Zero
   * when the caller lacks `organization.interventions.review` — the badge
   * only means something to a member who may actually review.
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public int $submittedInterventions = 0;
  // #endregion
}
