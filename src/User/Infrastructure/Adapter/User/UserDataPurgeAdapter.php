<?php

declare(strict_types=1);

namespace User\Infrastructure\Adapter\User;

use Authorization\Application\Port\Inbound\AuthorizationDataLifecyclePort;
use Doctrine\ORM\EntityManagerInterface;
use OAuth\Application\Port\Inbound\OAuthDataLifecyclePort;
use Otp\Application\Port\Inbound\OtpDataLifecyclePort;
use Otp\Application\Port\Inbound\Totp\TotpEnrollmentPurgePort;
use Session\Application\Port\Inbound\SessionDataLifecyclePort;
use Shared\Application\Port\Outbound\{FileCleanupPort, RootTransactionManagerPort};
use Shared\Application\Port\Outbound\Idempotency\HttpIdempotencyExecutionPort;
use TrustedDevice\Application\Port\Inbound\TrustedDeviceDataLifecyclePort;
use User\Application\Contract\User\AvatarPaths;
use User\Application\Port\Outbound\{AvatarPersistencePort, UserDataPurgePort};
use User\Infrastructure\Persistence\Doctrine\Record\UserPresencePreferenceRecord;

use function trim;

/**
 * Adapter UserDataPurgeAdapter.
 *
 * Purges user-linked data across
 * auth-related modules.
 *
 * @category Adapter
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UserDataPurgeAdapter implements UserDataPurgePort
{
  // #region Constructor
  /**
   * @param EntityManagerInterface $entityManager the explicit auth entity manager
   * @param TotpEnrollmentPurgePort $totpEnrollmentPurge the Otp-owned secret deletion capability
   */
  public function __construct(
    private EntityManagerInterface $entityManager,
    private TotpEnrollmentPurgePort $totpEnrollmentPurge,
    private AuthorizationDataLifecyclePort $authorizationLifecycle,
    private SessionDataLifecyclePort $sessionLifecycle,
    private OtpDataLifecyclePort $otpLifecycle,
    private TrustedDeviceDataLifecyclePort $trusteddeviceLifecycle,
    private OAuthDataLifecyclePort $oauthLifecycle,
    private AvatarPersistencePort $avatars,
    private FileCleanupPort $avatarCleanup,
    private ?RootTransactionManagerPort $rootTransaction = null,
    private ?HttpIdempotencyExecutionPort $execution = null,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method withUserLock.
   * {@inheritDoc}
   */
  public function withUserLock(string $userId, callable $operation): mixed
  {
    return null === $this->rootTransaction || null === $this->execution?->executionLease()
      ? $this->totpEnrollmentPurge->withUserLock($userId, $operation)
      : $this->rootTransaction->transactional(fn () => $this->totpEnrollmentPurge->withUserLock($userId, $operation));
  }

  /**
   * Method purgeForUser.
   *
   * Deletes user-owned records from the repositories managed by this adapter.
   *
   * @access public
   *
   * @param string $userId the user identifier whose data is purged
   *
   * @return void no return value
   */
  public function purgeForUser(string $userId): void
  {
    $normalizedUserId = trim($userId);
    if ('' === $normalizedUserId) {
      return;
    }

    foreach (AvatarPaths::variants($normalizedUserId, $this->avatars->generation($normalizedUserId)) as $path) {
      $this->avatarCleanup->request($path);
    }

    $this->deleteWhere(UserPresencePreferenceRecord::class, 'presence', 'presence.userId = :userId', ['userId' => $normalizedUserId]);

    $this->totpEnrollmentPurge->purgeForUser($normalizedUserId);
    $this->authorizationLifecycle->purgeForUser($normalizedUserId);
    $this->sessionLifecycle->purgeForUser($normalizedUserId);
    $this->otpLifecycle->purgeForUser($normalizedUserId);
    $this->trusteddeviceLifecycle->purgeForUser($normalizedUserId);
    $this->oauthLifecycle->purgeForUser($normalizedUserId);
  }

  /**
   * Method deleteWhere.
   *
   * Deletes records matching the supplied DQL predicate and applies each named query parameter.
   *
   * @access private
   *
   * @param class-string $entityClass the Doctrine entity class to delete
   * @param string $alias the query alias for the entity
   * @param string $where the DQL predicate limiting the deletion
   * @param array<string, mixed> $parameters named values bound to the predicate
   *
   * @return void
   */
  private function deleteWhere(string $entityClass, string $alias, string $where, array $parameters): void
  {
    $qb = $this->entityManager->createQueryBuilder();
    $qb->delete($entityClass, $alias)
      ->where($where);

    foreach ($parameters as $name => $value) {
      $qb->setParameter($name, $value);
    }

    $qb->getQuery()->execute();
  }
  // #endregion
}
