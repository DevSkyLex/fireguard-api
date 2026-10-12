<?php

declare(strict_types=1);

namespace Tests\Support\Message;

use Shared\Application\Message\QueryMessage;

/**
 * Class ContractQueryFixture.
 *
 * Publishes a concrete message schema for validated architectural descriptor examples.
 *
 * @category Test Support
 */
final readonly class ContractQueryFixture implements QueryMessage
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
