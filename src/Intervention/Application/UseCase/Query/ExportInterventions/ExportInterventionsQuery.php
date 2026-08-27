<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Query\ExportInterventions;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase ExportInterventionsQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ExportInterventionsQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $userId the caller's user id
   * @param string $organizationId the organization the export is scoped to
   * @param array<string, mixed> $filters the same filter shape {@see \Intervention\Application\UseCase\Query\Workflow\ListInterventionWorkflow\ListInterventionWorkflowQuery} accepts
   */
  public function __construct(
    public string $userId,
    public string $organizationId,
    public array $filters,
  ) {
  }
  // #endregion
}
