<?php

declare(strict_types=1);

namespace Intervention\Application\Contract\Publication;

/**
 * Class InterventionPublicationFactsPage
 *
 * Lists published dossiers with exact pagination and explicit missing-snapshot rows.
 *
 * @category Contract
 */
final readonly class InterventionPublicationFactsPage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Missing historical snapshots remain visible in the count and directory.
   *
   * @access public
   *
   * @param list<InterventionPublicationFacts> $items bounded publication facts
   * @param int $totalItems exact published intervention count
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
