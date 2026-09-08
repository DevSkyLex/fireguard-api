<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Query\ExportFacilities;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase ExportFacilitiesQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ExportFacilitiesQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $userId the caller's user id
   * @param string $organizationId the organization the export is scoped to
   * @param array<string, mixed> $filters the same filter shape {@see \Facility\Application\UseCase\Query\Facility\ListFacilities\ListFacilitiesQuery} accepts
   */
  public function __construct(
    public string $userId,
    public string $organizationId,
    public array $filters,
  ) {
  }
  // #endregion
}
