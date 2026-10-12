<?php

declare(strict_types=1);

namespace Auth\Infrastructure\Persistence\Doctrine\Repository;

use Auth\Application\Contract\Federation\{FederatedConnection, ProviderId};
use Auth\Application\Port\Outbound\Federation\FederatedIdentityRepositoryPort;
use Auth\Domain\Exception\Federation\FederatedAuthException;
use Auth\Infrastructure\Persistence\Doctrine\Record\FederatedIdentityRecord;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

use function array_map;
use function count;

/**
 * Class FederatedIdentityRepository
 *
 * Persists federated identity records and maps them to the auth application contract.
 *
 * @category Repository
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FederatedIdentityRepository implements FederatedIdentityRepositoryPort
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Receives the auth entity manager used to persist federated identity records.
   *
   * @access public
   *
   * @param EntityManagerInterface $entityManager the auth database entity manager
   *
   * @return void
   */
  public function __construct(private EntityManagerInterface $entityManager)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method hasIssuerConflict
   *
   * Generic provider identifiers remain pinned to the issuer recorded on their identities.
   *
   * @access public
   *
   * @param ProviderId $provider stable provider namespace
   * @param string $issuer verified issuer
   *
   * @return bool whether that namespace has a conflicting issuer
   */
  public function hasIssuerConflict(ProviderId $provider, string $issuer): bool
  {
    return false !== $this->entityManager->getConnection()->fetchOne(
      'SELECT id FROM federated_identities WHERE provider = :provider AND (issuer IS NULL OR issuer <> :issuer) LIMIT 1',
      ['provider' => $provider->value, 'issuer' => $issuer],
    );
  }

  /**
   * Method findBySubject
   *
   * Looks up the external identity by provider-issued subject and maps a match to its application contract.
   *
   * @access public
   *
   * @param ProviderId $provider the identity provider
   * @param string $subject the provider-issued subject identifier
   *
   * @return ?FederatedConnection the matching connection, or null
   */
  public function findBySubject(ProviderId $provider, string $subject): ?FederatedConnection
  {
    $record = $this->entityManager->getRepository(FederatedIdentityRecord::class)->findOneBy([
      'provider' => $provider->value,
      'subject' => $subject,
    ]);

    return $record instanceof FederatedIdentityRecord ? $this->toContract($record) : null;
  }

  /**
   * Method findForUserProvider
   *
   * Looks up the user's connection for one provider and maps a match to its application contract.
   *
   * @access public
   *
   * @param string $userId the local user identifier
   * @param ProviderId $provider the identity provider
   *
   * @return ?FederatedConnection the matching connection, or null
   */
  public function findForUserProvider(string $userId, ProviderId $provider): ?FederatedConnection
  {
    $record = $this->entityManager->getRepository(FederatedIdentityRecord::class)->findOneBy([
      'userId' => $userId,
      'provider' => $provider->value,
    ]);

    return $record instanceof FederatedIdentityRecord ? $this->toContract($record) : null;
  }

  /**
   * Method findForUser
   *
   * Lists the user's federated connections in connection-date order.
   *
   * @access public
   *
   * @param string $userId the local user identifier
   *
   * @return list<FederatedConnection> the user's connections ordered from oldest to newest
   */
  public function findForUser(string $userId): array
  {
    $records = $this->entityManager->getRepository(FederatedIdentityRecord::class)->findBy(
      ['userId' => $userId],
      ['connectedAt' => 'ASC'],
    );

    return array_map($this->toContract(...), $records);
  }

  /**
   * Method save
   *
   * Creates or updates the auth record for this external identity and flushes the change.
   *
   * @access public
   *
   * @param FederatedConnection $connection the connection to persist
   *
   * @return void
   */
  public function save(FederatedConnection $connection): void
  {
    $this->entityManager->wrapInTransaction(function () use ($connection): void {
      if (null !== $connection->issuer) {
        $this->entityManager->getConnection()->executeQuery(
          'SELECT pg_advisory_xact_lock(hashtextextended(:provider, 0))',
          ['provider' => 'federated-issuer:' . $connection->provider->value],
        );
        if ($this->hasIssuerConflict($connection->provider, $connection->issuer)) {
          throw new FederatedAuthException('issuer_mismatch', 'This sign-in provider configuration has changed.');
        }
      }
      $record = $this->entityManager->find(FederatedIdentityRecord::class, $connection->id);
      if (!$record instanceof FederatedIdentityRecord) {
        $record = new FederatedIdentityRecord();
        $record->id = $connection->id;
      }

      $record->userId = $connection->userId;
      $record->provider = $connection->provider->value;
      $record->subject = $connection->subject;
      $record->issuer = $connection->issuer;
      $record->email = $connection->email;
      $record->connectedAt = $connection->connectedAt;
      $record->lastUsedAt = $connection->lastUsedAt;
      $this->entityManager->persist($record);
      $this->entityManager->flush();
    });
  }

  /**
   * Method remove
   *
   * Deletes the stored connection when its record still exists.
   *
   * @access public
   *
   * @param FederatedConnection $connection the connection to remove
   *
   * @return void
   */
  public function remove(FederatedConnection $connection): void
  {
    $record = $this->entityManager->find(FederatedIdentityRecord::class, $connection->id);
    if ($record instanceof FederatedIdentityRecord) {
      $this->entityManager->remove($record);
      $this->entityManager->flush();
    }
  }

  /**
   * Method removePreservingAccess
   *
   * Locks the user's connections and refuses to remove the last sign-in method when no password is configured.
   *
   * @access public
   *
   * @param ProviderId $provider the provider connection to remove
   * @param string $userId the local user identifier
   * @param bool $passwordConfigured whether password sign-in remains available
   *
   * @return bool whether removal was permitted and completed
   */
  public function removePreservingAccess(ProviderId $provider, string $userId, bool $passwordConfigured): bool
  {
    return $this->entityManager->wrapInTransaction(function () use ($provider, $userId, $passwordConfigured): bool {
      /** @var list<FederatedIdentityRecord> $records */
      $records = $this->entityManager->createQueryBuilder()
        ->select('identity')
        ->from(FederatedIdentityRecord::class, 'identity')
        ->where('identity.userId = :userId')
        ->setParameter('userId', $userId)
        ->orderBy('identity.id', 'ASC')
        ->getQuery()
        ->setLockMode(LockMode::PESSIMISTIC_WRITE)
        ->getResult();

      $target = null;
      foreach ($records as $record) {
        if ($record->provider === $provider->value) {
          $target = $record;
        }
      }

      if (!$target instanceof FederatedIdentityRecord) {
        return true;
      }
      if (!$passwordConfigured && count($records) <= 1) {
        return false;
      }

      $this->entityManager->remove($target);
      $this->entityManager->flush();

      return true;
    });
  }

  /**
   * Method toContract
   *
   * Maps the persisted identity record to the application-owned connection contract.
   *
   * @access private
   *
   * @param FederatedIdentityRecord $record the persisted identity
   *
   * @return FederatedConnection the application connection contract
   */
  private function toContract(FederatedIdentityRecord $record): FederatedConnection
  {
    return new FederatedConnection(
      id: $record->id,
      userId: $record->userId,
      provider: ProviderId::from($record->provider),
      subject: $record->subject,
      email: $record->email,
      connectedAt: $record->connectedAt,
      lastUsedAt: $record->lastUsedAt,
      issuer: $record->issuer,
    );
  }
  // #endregion
}
