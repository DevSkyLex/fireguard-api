<?php

declare(strict_types=1);

namespace Organization\Domain\Model\Organization;

use DateTimeImmutable;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationName};

/** Persisted organization identity and original active-state fallback. */
final readonly class RestoredOrganizationCore
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Carries persisted organization identity and its creation metadata.
   *
   * @access public
   *
   * @param OrganizationId $id organization identifier
   * @param OrganizationName $name organization display name
   * @param string $createdByUserId user who created the organization
   * @param bool $isActive persisted active state used as a legacy fallback
   * @param DateTimeImmutable $createdAt original organization creation timestamp
   *
   * @return void
   */
  public function __construct(
    public OrganizationId $id,
    public OrganizationName $name,
    public string $createdByUserId,
    public bool $isActive,
    public DateTimeImmutable $createdAt,
  ) {
  }
  // #endregion
}
