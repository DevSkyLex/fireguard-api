<?php

declare(strict_types=1);

namespace Compliance\Application\UseCase\Query\GetFacilityCompliance;

use Compliance\Application\Contract\FacilityComplianceView;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase GetFacilityComplianceResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetFacilityComplianceResult implements ResultMessage
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $generatedAt ISO 8601 generation datetime
   * @param FacilityComplianceView $facility the single-facility compliance view
   */
  public function __construct(
    public string $generatedAt,
    public FacilityComplianceView $facility,
  ) {
  }
}
