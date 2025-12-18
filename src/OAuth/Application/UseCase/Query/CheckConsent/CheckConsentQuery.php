<?php

declare(strict_types=1);

namespace OAuth\Application\UseCase\Query\CheckConsent;

use Shared\Application\Message\QueryMessage;

/**
 * Query CheckConsentQuery
 * @final
 *
 * Query to check if user has granted consent.
 *
 * @category Query
 * @package OAuth\Application\UseCase\Query\CheckConsent
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CheckConsentQuery implements QueryMessage
{
  //#region Constructor
  /**
   * Constructor
   * 
   * Initializes a new instance of the 
   * CheckConsentQuery class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $userId The user ID.
   * @param string $clientId The client ID.
   * @param list<string> $requestedScopes The requested scopes.
   */
  public function __construct(
    public readonly string $userId,
    public readonly string $clientId,
    public readonly array $requestedScopes,
  ) {}
  //#endregion
}
