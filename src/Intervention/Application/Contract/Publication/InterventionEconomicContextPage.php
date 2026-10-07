<?php

declare(strict_types=1);

namespace Intervention\Application\Contract\Publication;

/**
 * Class InterventionEconomicContextPage
 *
 * Contains a bounded source page and an exact filtered count for explicit report narrowing.
 *
 * @category Contract
 */
final readonly class InterventionEconomicContextPage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * The count includes every match while items contain only the requested bounded page.
   *
   * @access public
   *
   * @param list<InterventionEconomicContext> $items bounded contexts
   * @param int $totalItems exact matching intervention count
   * @param int $page one-based page number
   * @param int $itemsPerPage bounded requested size
   *
   * @return void
   */
  public function __construct(public array $items, public int $totalItems, public int $page, public int $itemsPerPage)
  {
  }
  // #endregion
}
