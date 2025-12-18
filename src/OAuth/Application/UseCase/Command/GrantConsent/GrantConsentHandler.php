<?php

declare(strict_types=1);

namespace OAuth\Application\UseCase\Command\GrantConsent;

use OAuth\Application\Port\Outbound\ConsentRepositoryPort;
use OAuth\Domain\Model\Consent;
use OAuth\Domain\ValueObject\ConsentId;
use Shared\Application\Factory\UuidFactory;
use OAuth\Domain\ValueObject\Scopes;

/**
 * Handler GrantConsentHandler
 * @final
 *
 * Handles granting user consent.
 *
 * @category Handler
 * @package OAuth\Application\UseCase\Command\GrantConsent
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GrantConsentHandler implements \Shared\Application\Message\CommandHandler
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the
   * GrantConsentHandler class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param ConsentRepositoryPort $consentRepository The consent repository.
   * @param UuidFactory $uuidFactory The UUID factory.
   */
  public function __construct(
    private readonly ConsentRepositoryPort $consentRepository,
    private readonly UuidFactory $uuidFactory,
  ) {
  }
  //#endregion

  //#region Methods
  /**
   * Method __invoke
   *
   * Handles the GrantConsentCommand.
   *
   * @access public
   * @since 1.0.0
   *
   * @param GrantConsentCommand $command The command to handle.
   *
   * @return GrantConsentResult The result.
   */
  public function __invoke(GrantConsentCommand $command): GrantConsentResult
  {
    // Check if consent already exists
    $existingConsent = $this->consentRepository->findByUserAndClient(
      userId: $command->userId,
      clientId: $command->clientId,
    );

    $scopes = Scopes::fromArray(scopes: $command->scopes);

    if ($existingConsent !== null && !$existingConsent->isRevoked()) {
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
  //#endregion
}
