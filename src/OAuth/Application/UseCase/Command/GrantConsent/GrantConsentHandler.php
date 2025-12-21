<?php

declare(strict_types=1);

namespace OAuth\Application\UseCase\Command\GrantConsent;

use OAuth\Application\Port\Outbound\ConsentRepositoryPort;
use OAuth\Domain\Model\Consent;
use OAuth\Domain\ValueObject\ConsentId;
use OAuth\Domain\ValueObject\Scopes;
use Shared\Application\Factory\UuidFactory;

/**
 * Handler GrantConsentHandler.
 *
 * @category Handler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GrantConsentHandler implements \Shared\Application\Message\CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * GrantConsentHandler class.
   *
   * @since 1.0.0
   *
   * @param ConsentRepositoryPort $consentRepository the consent repository
   * @param UuidFactory $uuidFactory the UUID factory
   */
  public function __construct(
    private readonly ConsentRepositoryPort $consentRepository,
    private readonly UuidFactory $uuidFactory,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Handles the GrantConsentCommand.
   *
   * @since 1.0.0
   *
   * @param GrantConsentCommand $command the command to handle
   *
   * @return GrantConsentResult the result
   */
  public function __invoke(GrantConsentCommand $command): GrantConsentResult
  {
    // Check if consent already exists
    $existingConsent = $this->consentRepository->findByUserAndClient(
      userId: $command->userId,
      clientId: $command->clientId,
    );

    $scopes = Scopes::fromArray(scopes: $command->scopes);

    if (null !== $existingConsent && !$existingConsent->isRevoked()) {
      // Update existing consent with new scopes
      $existingConsent->updateScopes(scopes: $scopes);
      $this->consentRepository->save(consent: $existingConsent);

      return new GrantConsentResult(
        consentId: (string) $existingConsent->id(),
        isNew: false,
      );
    }

    // Create new consent
    $consentId = $this->uuidFactory->create(ConsentId::class);

    $consent = Consent::grant(
      id: $consentId,
      userId: $command->userId,
      clientId: $command->clientId,
      scopes: $scopes,
    );

    $this->consentRepository->save(consent: $consent);

    return new GrantConsentResult(
      consentId: (string) $consentId,
      isNew: true,
    );
  }
  // #endregion
}
