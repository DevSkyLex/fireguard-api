<?php

declare(strict_types=1);

namespace OAuth\Application\UseCase\Query\CheckConsent;

use Shared\Application\Message\QueryMessage;

/**
 * Query CheckConsentQuery.
 *
 * @category Query
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CheckConsentQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * CheckConsentQuery class.
   *
   * @since 1.0.0
   *
   * @param string $userId the user ID
   * @param string $clientId the client ID
   * @param list<string> $requestedScopes the requested scopes
   */
  public function __construct(
    public readonly string $userId,
    public readonly string $clientId,
    public readonly array $requestedScopes,
  ) {
  }
  // #endregion
}
