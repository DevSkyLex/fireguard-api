<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Query\Response\ListInspectionResponses;

use Inspection\Application\Contract\Response\InspectionResponseView;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase ListInspectionResponsesResult.
 *
 * Carries the page AND the total, because the endpoint answers with a Hydra
 * paginator: a page without its total cannot say how many others there are.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListInspectionResponsesResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @param list<InspectionResponseView> $views the page of responses
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
