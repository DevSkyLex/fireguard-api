<?php

declare(strict_types=1);

namespace Intervention\Application\Service;

use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\Port\Outbound\OrganizationMemberRepositoryPort;
use Organization\Domain\ValueObject\OrganizationId;

use function array_intersect;
use function array_unique;
use function array_values;

/**
 * Service InterventionReviewerRecipientResolver.
 *
 * Resolves the recipients of an intervention submission notification: the
 * organization's active members whose effective permissions grant
 * `organization.interventions.review` directly or through a wildcard.
 * Mirrors the administrator-detection rule
 * {@see InterventionRecurrenceRecipientResolver} uses for
 * `organization.interventions.plan`, adapted to the review permission that
 * governs intervention submissions.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InterventionReviewerRecipientResolver
{
  /**
   * Granted permission patterns that satisfy organization.interventions.review.
   *
   * @var list<string>
   */
  private const array REVIEW_GRANTING_PERMISSIONS = [
    'organization.interventions.review',
    'organization.interventions.*',
    'organization.*',
    '*',
    '*.*',
    '*.*.*',
  ];

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param OrganizationMemberRepositoryPort $members the organization member repository port
   * @param OrganizationAuthorizationPort $authorization the organization authorization port
   */
  public function __construct(
    private OrganizationMemberRepositoryPort $members,
    private OrganizationAuthorizationPort $authorization,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method organizationReviewers.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   *
   * @return list<string> the active reviewers' user identifiers
   */
  public function organizationReviewers(string $organizationId): array
  {
    $organization = OrganizationId::fromString($organizationId);
    $reviewerUserIds = [];

    foreach ($this->members->findByOrganizationId($organization) as $member) {
      if (!$member->isActive()) {
        continue;
      }

      $permissions = $this->authorization->getUserPermissions($member->userId(), $organizationId);
      if ([] !== array_intersect($permissions, self::REVIEW_GRANTING_PERMISSIONS)) {
        $reviewerUserIds[] = $member->userId();
      }
    }

    return array_values(array_unique($reviewerUserIds));
  }
  // #endregion
}
