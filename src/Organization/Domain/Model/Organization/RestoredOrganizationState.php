<?php

declare(strict_types=1);

namespace Organization\Domain\Model\Organization;

use DateTimeImmutable;
use Organization\Domain\ValueObject\{OrganizationSlug, OrganizationStatus};

/** Optional persisted state with the same legacy fallbacks as the aggregate. */
final readonly class RestoredOrganizationState
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Carries optional persisted state while preserving aggregate legacy defaults.
   *
   * @access public
   *
   * @param ?DateTimeImmutable $updatedAt optional last update timestamp
   * @param ?string $ownerUserId optional organization owner user identifier
   * @param ?OrganizationSlug $slug optional organization slug
   * @param ?OrganizationStatus $status optional lifecycle status
   *
   * @return void
   */
  public function __construct(
    public ?DateTimeImmutable $updatedAt = null,
    public ?string $ownerUserId = null,
    public ?OrganizationSlug $slug = null,
    public ?OrganizationStatus $status = null,
  ) {
  }
  // #endregion
}
