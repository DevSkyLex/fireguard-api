<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Query\CheckConsent;

use Auth\Application\Port\Outbound\ConsentRepositoryPort;
use Shared\Domain\ValueObject\Scopes;
use Shared\Application\Message\QueryHandler;

/**
 * Handler CheckConsentHandler
 * @final
 *
 * Handles checking user consent.
 *
 * @category Handler
 * @package Auth\Application\UseCase\Query\CheckConsent
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CheckConsentHandler implements QueryHandler
{
  //#region Constructor
  /**
   * Constructor
   * 
   * Initializes a new instance of the 
   * CheckConsentHandler class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param ConsentRepositoryPort $consentRepository The consent repository.
   */
  public function __construct(
    private readonly ConsentRepositoryPort $consentRepository,
  ) {}
  //#endregion

  //#region Methods
  /**
   * Method __invoke
   *
   * Handles the CheckConsentQuery.
   *
   * @access public
   * @since 1.0.0
   *
   * @param CheckConsentQuery $query The query to handle.
   *
   * @return CheckConsentResult The result.
   */
  public function __invoke(CheckConsentQuery $query): CheckConsentResult
  {
    $consent = $this->consentRepository->findByUserAndClient(
      userId: $query->userId,
      clientId: $query->clientId,
    );

    if ($consent === null || $consent->isRevoked()) {
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
  //#endregion
}
