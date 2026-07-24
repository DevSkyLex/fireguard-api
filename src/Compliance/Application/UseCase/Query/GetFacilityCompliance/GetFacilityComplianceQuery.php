<?php

declare(strict_types=1);

namespace Compliance\Application\UseCase\Query\GetFacilityCompliance;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase GetFacilityComplianceQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetFacilityComplianceQuery implements QueryMessage
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $facilityId the facility identifier
   * @param string $userId the authenticated user identifier
   */
  public function __construct(
    public string $organizationId,
    public string $facilityId,
    public string $userId,
  ) {
  }
}
