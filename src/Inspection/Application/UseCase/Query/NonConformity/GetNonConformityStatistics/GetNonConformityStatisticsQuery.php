<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Query\NonConformity\GetNonConformityStatistics;

use DateTimeImmutable;
use Shared\Application\Message\QueryMessage;

/**
 * UseCase GetNonConformityStatisticsQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetNonConformityStatisticsQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $userId the requesting user identifier
   * @param string $organizationId the organization identifier
   * @param ?DateTimeImmutable $from inclusive `createdAt` lower bound (default: null)
   * @param ?DateTimeImmutable $to inclusive `createdAt` upper bound (default: null)
   */
  public function __construct(
    public string $userId,
    public string $organizationId,
    public ?DateTimeImmutable $from = null,
    public ?DateTimeImmutable $to = null,
  ) {
  }
  // #endregion
}
