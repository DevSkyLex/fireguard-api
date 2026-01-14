<?php

declare(strict_types=1);

namespace Otp\Application\UseCase\Query\Config\ListPurposes;

use Shared\Application\Message\ResultMessage;

/**
 * Result ListPurposesResult.
 *
 * @category Result
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListPurposesResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @param list<PurposeResult> $items the purpose items
   */
  public function __construct(
    public array $items,
  ) {
  }
  // #endregion
}
