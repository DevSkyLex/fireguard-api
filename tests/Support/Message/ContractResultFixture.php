<?php

declare(strict_types=1);

namespace Tests\Support\Message;

use Shared\Application\Message\ResultMessage;

/**
 * Class ContractResultFixture.
 *
 * Publishes a concrete message schema for validated architectural descriptor examples.
 *
 * @category Test Support
 */
final readonly class ContractResultFixture implements ResultMessage
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * @param string $value the immutable fixture value
   */
  public function __construct(public string $value)
  {
  }
  // #endregion
}
