<?php

declare(strict_types=1);

namespace Intervention\Application\Contract\Result;

use DateTimeImmutable;

/**
 * Class InterventionInspectionResult
 *
 * Exposes the inspection owner's recorded fact without leaking its persistence model.
 *
 * @category Contract
 */
final readonly class InterventionInspectionResult
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param string $id inspection identifier
   * @param string $equipmentId inspected equipment
   * @param string $result pass, fail or partial
   * @param string $status inspection lifecycle status
   * @param DateTimeImmutable $performedAt actual inspection instant
   * @param ?string $authorId recorded inspector account identifier
   * @param ?string $notes recorded inspection notes
   *
   * @return void
   */
  public function __construct(public string $id, public string $equipmentId, public string $result, public string $status, public DateTimeImmutable $performedAt, public ?string $authorId, public ?string $notes)
  {
  }
  // #endregion
}
