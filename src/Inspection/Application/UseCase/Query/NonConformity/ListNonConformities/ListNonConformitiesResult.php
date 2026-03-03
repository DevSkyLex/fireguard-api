<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Query\NonConformity\ListNonConformities;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase ListNonConformitiesResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListNonConformitiesResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param list<NonConformityResult> $nonConformities the non-conformity list
   */
  public function __construct(
    public array $nonConformities,
  ) {
  }
  // #endregion
}
