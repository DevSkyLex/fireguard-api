<?php

declare(strict_types=1);

namespace Auth\Infrastructure\Persistence\Doctrine\Repository;

use Auth\Application\Port\Outbound\AuthCodeRepositoryPort;
use Auth\Domain\Model\AuthCode;
use Auth\Infrastructure\Persistence\Doctrine\Record\AuthCodeRecord;
use Doctrine\ORM\EntityManagerInterface;
use Shared\Domain\ValueObject\OAuthClientIdentifier;
use Shared\Domain\ValueObject\Scopes;

/**
 * Repository AuthCodeRepository
 * @final
 *
 * Doctrine implementation of AuthCodeRepositoryPort.
 *
 * @category Repository
 * @package Auth\Infrastructure\Persistence\Doctrine\Repository
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AuthCodeRepository implements AuthCodeRepositoryPort
{
  //#region Constructor
  /**
   * Constructor
   * 
   * Initializes a new instance of the 
   * AuthCodeRepository class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager The entity manager.
   */
  public function __construct(
    private EntityManagerInterface $entityManager
  ) {
  }
  //#endregion

  //#region Methods
  /**
   * Method save
   * {@inheritDoc}
   * 
   * Saves an auth code to the database.
   *
   * @access public
   * @since 1.0.0
   *
   * @param AuthCode $authCode The auth code to save.
   */
  public function save(AuthCode $authCode): void
  {
    $record = $this->entityManager->find(
      className: AuthCodeRecord::class,
      id: $authCode->identifier()
    );

    if (!$record) {
      $record = new AuthCodeRecord();
      $record->identifier = $authCode->identifier();
    }

    $record->clientIdentifier = (string) $authCode->clientIdentifier();
    $record->userIdentifier = $authCode->userIdentifier();
    $record->scopes = $authCode->scopes()->toArray();
    $record->redirectUri = $authCode->redirectUri();
    $record->expiry = $authCode->expiryDateTime();
    $record->isRevoked = $authCode->isRevoked();

    $this->entityManager->persist($record);
    $this->entityManager->flush();
  }

  /**
   * Method find
   * {@inheritDoc}
   * 
   * Finds an auth code by its identifier.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $identifier The identifier of the auth code to find.
   */
  public function find(string $identifier): ?AuthCode
  {
    $record = $this->entityManager->find(
      className: AuthCodeRecord::class,
      id: $identifier
    );

    if (!$record)
      return null;

    return new AuthCode(
      identifier: $record->identifier,
      expiryDateTime: $record->expiry,
      clientIdentifier: new OAuthClientIdentifier($record->clientIdentifier),
      userIdentifier: $record->userIdentifier,
      scopes: Scopes::fromArray($record->scopes),
      redirectUri: $record->redirectUri,
      isRevoked: $record->isRevoked
    );
  }
  //#endregion
}
