<?php

declare(strict_types=1);

namespace Session\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\{EntityManagerInterface, EntityRepository};
use Session\Application\Port\Outbound\SessionRepositoryPort;
use Session\Domain\Model\Session\Session;
use Session\Domain\ValueObject\SessionId;
use Session\Infrastructure\Persistence\Doctrine\Mapper\SessionMapper;
use Session\Infrastructure\Persistence\Doctrine\Record\SessionRecord;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

use function array_map;
use function is_int;

/**
 * Repository SessionRepository.
 *
 * @category Repository
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class SessionRepository implements SessionRepositoryPort
{
  // #region Properties
  /**
   * Property repository.
   *
   * The Doctrine entity repository.
   *
   * @since 1.0.0
   *
   * @var EntityRepository<SessionRecord>
   */
  private EntityRepository $repository;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager the entity manager
   */
  public function __construct(
    private readonly EntityManagerInterface $entityManager,
  ) {
    $this->repository = $entityManager->getRepository(className: SessionRecord::class);
  }
  // #endregion

  // #region Methods
  /**
   * Method rotateTokens.
   *
   * Atomically replaces the active token pair only when the supplied pair is still current.
   *
   * @access public
   *
   * @param string $currentRefreshTokenId the current refresh token identifier
   * @param string $currentAccessTokenId the current access token identifier
   * @param string $newAccessTokenId the replacement access token identifier
   * @param string $newRefreshTokenId the replacement refresh token identifier
   *
   * @return bool whether the current, non-revoked token pair was rotated
   */
  public function rotateTokens(string $currentRefreshTokenId, string $currentAccessTokenId, string $newAccessTokenId, string $newRefreshTokenId): bool
  {
    // The conditional UPDATE serializes refreshes and revocations on the same row.
    // Never fall back to an access-token lookup: a used refresh token must fail.
    return 1 === $this->entityManager->getConnection()->executeStatement(
      'UPDATE sessions SET access_token_id = ?, refresh_token_id = ?, last_activity_at = CURRENT_TIMESTAMP WHERE refresh_token_id = ? AND access_token_id = ? AND revoked_at IS NULL',
      [$newAccessTokenId, $newRefreshTokenId, $currentRefreshTokenId, $currentAccessTokenId],
    );
  }

  /**
   * Method save
   * {@inheritDoc}
   */
  public function save(Session $session): void
  {
    $record = SessionMapper::toRecord(session: $session);
    $existingRecord = $this->repository->find(id: $record->id);

    if (null !== $existingRecord && $session->isRevoked()) {
      // A stale snapshot must not restore a token pair rotated by another request.
      $this->entityManager->getConnection()->executeStatement(
        "UPDATE sessions SET revoked_at = COALESCE(revoked_at, :revokedAt), metadata = (metadata::jsonb - 'country' - 'city')::json WHERE id = :id",
        ['revokedAt' => $record->revokedAt, 'id' => $record->id],
        ['revokedAt' => Types::DATETIME_IMMUTABLE, 'id' => UuidType::NAME],
      );
      $this->entityManager->refresh($existingRecord);

      return;
    }

    if ($existingRecord) {
      $existingRecord->accessTokenId = $record->accessTokenId;
      $existingRecord->refreshTokenId = $record->refreshTokenId;
      $existingRecord->lastActivityAt = $record->lastActivityAt;
      $existingRecord->revokedAt = $record->revokedAt;
      $existingRecord->metadata = $record->metadata;
    } else {
      $this->entityManager->persist(object: $record);
    }

    $this->entityManager->flush();
  }

  /**
   * Method findById
   * {@inheritDoc}
   */
  public function findById(SessionId $id): ?Session
  {
    $record = $this->repository->find(id: $id->value);

    if (!$record) {
      return null;
    }

    return SessionMapper::toDomain(record: $record);
  }

  /**
   * Method findByUserId
   * {@inheritDoc}
   */
  public function findByUserId(string $userId): array
  {
    $records = $this->repository->findBy(
      criteria: ['userId' => $userId],
      orderBy: ['lastActivityAt' => 'DESC'],
    );

    return array_map(
      callback: fn (SessionRecord $record): Session => SessionMapper::toDomain(record: $record),
      array: $records,
    );
  }

  /**
   * Method findActiveByUserId
   * {@inheritDoc}
   */
  public function findActiveByUserId(string $userId): array
  {
    $records = $this->repository->findBy(
      criteria: ['userId' => $userId, 'revokedAt' => null],
      orderBy: ['lastActivityAt' => 'DESC'],
    );

    return array_map(
      callback: fn (SessionRecord $record): Session => SessionMapper::toDomain(record: $record),
      array: $records,
    );
  }

  /**
   * Method findByAccessTokenId
   * {@inheritDoc}
   */
  public function findByAccessTokenId(string $accessTokenId): ?Session
  {
    $record = $this->repository->findOneBy(criteria: ['accessTokenId' => $accessTokenId]);

    if (!$record) {
      return null;
    }

    // Revocation and rotation use conditional SQL, so the identity map may be stale.
    $this->entityManager->refresh($record);

    return SessionMapper::toDomain(record: $record);
  }

  /**
   * Method findByRefreshTokenId
   * {@inheritDoc}
   */
  public function findByRefreshTokenId(string $refreshTokenId): ?Session
  {
    $record = $this->repository->findOneBy(criteria: ['refreshTokenId' => $refreshTokenId]);

    if (!$record) {
      return null;
    }

    $this->entityManager->refresh($record);

    return SessionMapper::toDomain(record: $record);
  }

  /**
   * Method revokeAllForUser
   * {@inheritDoc}
   */
  public function revokeAllForUser(string $userId): int
  {
    return $this->revokeActiveForUser($userId);
  }

  /**
   * Method revokeAllForUserExcept
   * {@inheritDoc}
   */
  public function revokeAllForUserExcept(string $userId, string $exceptSessionId): int
  {
    return $this->revokeActiveForUser($userId, $exceptSessionId);
  }

  /**
   * Method delete
   * {@inheritDoc}
   */
  public function delete(SessionId $id): void
  {
    $record = $this->repository->find(id: $id->value);

    if ($record) {
      $this->entityManager->remove(object: $record);
      $this->entityManager->flush();
    }
  }

  /**
   * Removes location keys while preserving all other metadata and token state.
   *
   * @since 1.0.0
   *
   * @param string $userId owning account
   *
   * @return int erased row count
   */
  public function purgeLocations(?string $userId = null): int
  {
    $where = null === $userId ? 'revoked_at IS NOT NULL' : 'user_id = :userId';

    return (int) $this->entityManager->getConnection()->executeStatement(
      "UPDATE sessions SET metadata = (metadata::jsonb - 'country' - 'city')::json WHERE (" . $where . ") AND (jsonb_exists(metadata::jsonb, 'country') OR jsonb_exists(metadata::jsonb, 'city'))",
      null === $userId ? [] : ['userId' => $userId],
    );
  }

  /**
   * Revokes and strips geography in one PostgreSQL update, serialized with token rotation.
   *
   * @since 1.0.0
   *
   * @param string $userId owning account
   * @param string|null $exceptSessionId optional current session; non-UUID IDs exclude nothing
   *
   * @return int newly revoked session count
   */
  private function revokeActiveForUser(string $userId, ?string $exceptSessionId = null): int
  {
    $sql = "UPDATE sessions SET revoked_at = CURRENT_TIMESTAMP, metadata = (metadata::jsonb - 'country' - 'city')::json WHERE user_id = :userId AND revoked_at IS NULL";
    $parameters = ['userId' => $userId];
    $types = [];
    if (null !== $exceptSessionId && Uuid::isValid($exceptSessionId)) {
      $sql .= ' AND id != :exceptSessionId';
      $parameters['exceptSessionId'] = Uuid::fromString($exceptSessionId);
      $types['exceptSessionId'] = UuidType::NAME;
    }

    $result = $this->entityManager->getConnection()->executeStatement($sql, $parameters, $types);

    return is_int($result) ? $result : 0;
  }
  // #endregion
}
