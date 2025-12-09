<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Command\GrantConsent;

use Shared\Application\Message\ResultMessage;

/**
 * Result GrantConsentResult
 * @final
 *
 * Result of granting consent.
 *
 * @category Result
 * @package Auth\Application\UseCase\Command\GrantConsent
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GrantConsentResult implements ResultMessage
{
  //#region Constructor
  /**
   * Constructor
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $consentId The consent ID.
   * @param bool $isNew Whether this is a new consent.
   */
  public function __construct(
    public string $consentId,
    public bool $isNew,
  ) {
  }
  //#endregion
}
