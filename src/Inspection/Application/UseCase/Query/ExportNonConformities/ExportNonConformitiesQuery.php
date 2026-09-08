<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Query\ExportNonConformities;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase ExportNonConformitiesQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ExportNonConformitiesQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $userId the caller's user id
   * @param string $organizationId the organization the export is scoped to
   * @param array<string, mixed> $filters the same `severity`/`status` filter subset {@see \Inspection\Presentation\Api\Provider\NonConformity\ListOrganizationNonConformitiesProvider} accepts
   */
  public function __construct(
    public string $userId,
    public string $organizationId,
    public array $filters,
  ) {
  }
  // #endregion
}
