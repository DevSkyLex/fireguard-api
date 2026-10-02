<?php

declare(strict_types=1);

namespace OAuth\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\ORM\EntityManagerInterface;
use OAuth\Application\Port\Outbound\Token\{GrantLifecyclePort, UserTokenRevocationPort};
use OAuth\Infrastructure\Persistence\Doctrine\Record\{AccessTokenRecord, AuthCodeRecord, RefreshTokenRecord};

/**
 * Class UserTokenRevocationRepository
 *
 * Revokes all user-bound OAuth grant families in one auth-database transaction.
 *
 * @category Repository
 */
final readonly class UserTokenRevocationRepository implements UserTokenRevocationPort
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param EntityManagerInterface $entityManager the owning auth entity manager
   *
   * @return void
   */
  public function __construct(private EntityManagerInterface $entityManager, private GrantLifecyclePort $grantLifecycle)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method revokeForUser
   *
   * Includes refresh tokens whose original access token was already revoked or expired.
   *
   * @access public
   *
   * @param string $userId the account identifier
   *
   * @return list<string> identifiers committed as revoked
   */
  public function revokeForUser(string $userId): array
  {
    return $this->entityManager->wrapInTransaction(function () use ($userId): array {
      $this->grantLifecycle->lockUser($userId);
      $identifiers = [];
      $accessIdentifiers = [];
      foreach ($this->entityManager->getRepository(AccessTokenRecord::class)->findBy(['userIdentifier' => $userId]) as $record) {
        $record->isRevoked = true;
        $identifiers[] = $record->identifier;
        $accessIdentifiers[] = $record->identifier;
      }
      if ([] !== $accessIdentifiers) {
        foreach ($this->entityManager->getRepository(RefreshTokenRecord::class)->findBy(['accessTokenIdentifier' => $accessIdentifiers]) as $record) {
          $record->isRevoked = true;
          $identifiers[] = $record->identifier;
        }
      }
      foreach ($this->entityManager->getRepository(AuthCodeRecord::class)->findBy(['userIdentifier' => $userId]) as $record) {
        $record->isRevoked = true;
        $identifiers[] = $record->identifier;
      }

      return $identifiers;
    });
  }
  // #endregion
}
