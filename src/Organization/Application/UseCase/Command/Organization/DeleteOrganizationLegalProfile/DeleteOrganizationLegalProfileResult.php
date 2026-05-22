<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Organization\DeleteOrganizationLegalProfile;

use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase DeleteOrganizationLegalProfileResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteOrganizationLegalProfileResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * DeleteOrganizationLegalProfileResult class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization ID
   * @param DateTimeImmutable $deletedAt the deletion date
   */
  public function __construct(
    public string $organizationId,
    public DateTimeImmutable $deletedAt,
  ) {
  }
  // #endregion
}
