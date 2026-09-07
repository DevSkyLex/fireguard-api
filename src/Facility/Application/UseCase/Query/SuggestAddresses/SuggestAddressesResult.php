<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Query\SuggestAddresses;

use Facility\Application\Contract\Geocoding\AddressSuggestion;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase SuggestAddressesResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class SuggestAddressesResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param list<AddressSuggestion> $suggestions the matching addresses
   */
  public function __construct(public array $suggestions)
  {
  }
  // #endregion
}
