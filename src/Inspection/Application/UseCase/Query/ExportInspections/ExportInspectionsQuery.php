<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Query\ExportInspections;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase ExportInspectionsQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ExportInspectionsQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $userId the caller's user id
   * @param string $organizationId the organization the export is scoped to
   * @param array<string, mixed> $filters the same filter subset {@see \Inspection\Presentation\Api\Provider\Inspection\ListInspectionsProvider} accepts, minus `inspectorType` and free-text `search`
   */
  public function __construct(
    public string $userId,
    public string $organizationId,
    public array $filters,
  ) {
  }
  // #endregion
}
