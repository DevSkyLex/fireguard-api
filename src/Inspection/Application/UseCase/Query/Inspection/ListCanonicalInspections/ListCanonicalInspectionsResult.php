<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Query\Inspection\ListCanonicalInspections;

use Inspection\Application\Contract\Inspection\CanonicalInspectionReadView;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase ListCanonicalInspectionsResult.
 *
 * Carries the page AND the total, because the endpoint answers with a Hydra
 * paginator.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListCanonicalInspectionsResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @param list<CanonicalInspectionReadView> $views the page of inspections
   * @param int $page the 1-based page number
   * @param int $itemsPerPage the page size
   * @param int $total the total row count across every page
   */
  public function __construct(
    public array $views,
    public int $page,
    public int $itemsPerPage,
    public int $total,
  ) {
  }
  // #endregion
}
