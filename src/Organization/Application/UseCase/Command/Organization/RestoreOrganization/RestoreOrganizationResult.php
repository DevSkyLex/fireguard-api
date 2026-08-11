<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Organization\RestoreOrganization;

use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase RestoreOrganizationResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RestoreOrganizationResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the RestoreOrganizationResult class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $status the organization status after the operation
   * @param DateTimeImmutable $updatedAt the organization's last update timestamp
   */
  public function __construct(
    public string $organizationId,
    public string $status,
    public DateTimeImmutable $updatedAt,
  ) {
  }
  // #endregion
}
