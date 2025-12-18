<?php

declare(strict_types=1);

namespace OAuth\Application\UseCase\Query\CheckConsent;

use Shared\Application\Message\ResultMessage;

/**
 * Result CheckConsentResult
 * @final
 *
 * Result of checking consent.
 *
 * @category Result
 * @package OAuth\Application\UseCase\Query\CheckConsent
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CheckConsentResult implements ResultMessage  
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the 
   * CheckConsentResult class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param bool $hasConsent Whether consent exists.
   * @param list<string> $grantedScopes The granted scopes.
   * @param list<string> $missingScopes The missing scopes.
   * @param bool $requiresConsentScreen Whether consent screen should be shown.
   */
  public function __construct(
    public readonly bool $hasConsent,
    public readonly array $grantedScopes,
    public readonly array $missingScopes,
    public readonly bool $requiresConsentScreen,
  ) {}
  //#endregion
}
