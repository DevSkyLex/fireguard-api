<?php

declare(strict_types=1);

namespace OAuth\Application\UseCase\Query\CheckConsent;

use OAuth\Application\Port\Outbound\ConsentRepositoryPort;
use OAuth\Domain\ValueObject\Scopes;
use Shared\Application\Message\QueryHandler;

use function array_diff;
use function array_values;

/**
 * Handler CheckConsentHandler.
 *
 * @category Handler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CheckConsentHandler implements QueryHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * CheckConsentHandler class.
   *
   * @since 1.0.0
   *
   * @param ConsentRepositoryPort $consentRepository the consent repository
   */
  public function __construct(
    private readonly ConsentRepositoryPort $consentRepository,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Handles the CheckConsentQuery.
   *
   * @since 1.0.0
   *
   * @param CheckConsentQuery $query the query to handle
   *
   * @return CheckConsentResult the result
   */
  public function __invoke(CheckConsentQuery $query): CheckConsentResult
  {
    $consent = $this->consentRepository->findByUserAndClient(
      userId: $query->userId,
      clientId: $query->clientId,
    );

    if (null === $consent || $consent->isRevoked()) {
      return new CheckConsentResult(
        hasConsent: false,
        grantedScopes: [],
        missingScopes: $query->requestedScopes,
        requiresConsentScreen: true,
      );
    }

    $requestedScopes = Scopes::fromArray(scopes: $query->requestedScopes);
    $hasAllScopes = $consent->containsAllScopes(requestedScopes: $requestedScopes);

    $grantedScopes = $consent->scopes()->toArray();
    $missingScopes = array_values(array_diff($query->requestedScopes, $grantedScopes));

    return new CheckConsentResult(
      hasConsent: true,
      grantedScopes: $grantedScopes,
      missingScopes: $missingScopes,
      requiresConsentScreen: !$hasAllScopes,
    );
  }
  // #endregion
}
