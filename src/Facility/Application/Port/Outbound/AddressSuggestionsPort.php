<?php

declare(strict_types=1);

namespace Facility\Application\Port\Outbound;

use Facility\Application\Contract\Geocoding\AddressSuggestion;
use Facility\Domain\Exception\FacilityAddressSuggestionsUnavailableException;

/**
 * Outbound Port AddressSuggestionsPort.
 *
 * @category Outbound Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface AddressSuggestionsPort
{
  // #region Methods
  /**
   * Method suggest.
   *
   * @since 1.0.0
   *
   * @param string $query the normalized partial postal address
   *
   * @throws FacilityAddressSuggestionsUnavailableException when no reliable provider response is available
   *
   * @return list<AddressSuggestion> up to five concrete addresses; empty means a successful search without matches
   */
  public function suggest(string $query): array;
  // #endregion
}
