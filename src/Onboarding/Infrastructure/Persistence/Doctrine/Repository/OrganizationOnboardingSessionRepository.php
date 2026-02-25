<?php

declare(strict_types=1);

namespace Onboarding\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\{EntityManagerInterface, EntityRepository};
use Onboarding\Application\Port\Outbound\OrganizationOnboardingSessionRepositoryPort;
use Onboarding\Domain\Model\OrganizationOnboardingSession\OrganizationOnboardingSession;
use Onboarding\Infrastructure\Persistence\Doctrine\Mapper\OrganizationOnboardingSessionMapper;
use Onboarding\Infrastructure\Persistence\Doctrine\Record\OrganizationOnboardingSessionRecord;

/**
 * Repository OrganizationOnboardingSessionRepository.
 *
 * @category Repository
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationOnboardingSessionRepository implements OrganizationOnboardingSessionRepositoryPort
{
  // #region Properties
  /**
   * @var EntityRepository<OrganizationOnboardingSessionRecord>
   */
  private EntityRepository $repository;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager the Doctrine entity manager
   */
  public function __construct(
    private readonly EntityManagerInterface $entityManager,
  ) {
    $this->repository = $this->entityManager->getRepository(OrganizationOnboardingSessionRecord::class);
  }
  // #endregion

  // #region Methods
  /**
   * Method save.
   *
   * Performs an insert-or-update for the given session.
   * Handles concurrent inserts via UniqueConstraintViolationException: when two
   * simultaneous requests both find no existing record and both attempt an insert,
   * only one succeeds; the other catches the exception, clears the identity map,
   * reloads the record and applies the update.
   *
   * @since 1.0.0
   *
   * @param OrganizationOnboardingSession $session the onboarding session aggregate
   */
  public function save(OrganizationOnboardingSession $session): void
  {
    $record = OrganizationOnboardingSessionMapper::toRecord($session);
    $existing = $this->repository->findOneBy(['userId' => $record->userId]);

    if ($existing instanceof OrganizationOnboardingSessionRecord) {
      $this->hydrateRecord($existing, $record);
      $this->entityManager->flush();

      return;
    }

    try {
      $this->entityManager->persist($record);
      $this->entityManager->flush();
    } catch (UniqueConstraintViolationException) {
      // A concurrent request inserted the record between our findOneBy and persist.
      // Clear the identity map to discard the detached $record, reload and update.
      $this->entityManager->clear();
      $existing = $this->repository->findOneBy(['userId' => $record->userId]);
      if ($existing instanceof OrganizationOnboardingSessionRecord) {
        $this->hydrateRecord($existing, $record);
        $this->entityManager->flush();
      }
    }
  }

  /**
   * Method findByUserId.
   *
   * @since 1.0.0
   *
   * @param string $userId the authenticated user identifier
   *
   * @return ?OrganizationOnboardingSession the onboarding session when found
   */
  public function findByUserId(string $userId): ?OrganizationOnboardingSession
  {
    $record = $this->repository->findOneBy(['userId' => $userId]);

    if (!$record instanceof OrganizationOnboardingSessionRecord) {
      return null;
    }

    return OrganizationOnboardingSessionMapper::toDomain($record);
  }

  /**
   * Method deleteByUserId.
   *
   * @since 1.0.0
   *
   * @param string $userId the authenticated user identifier
   */
  public function deleteByUserId(string $userId): void
  {
    $record = $this->repository->findOneBy(['userId' => $userId]);

    if ($record instanceof OrganizationOnboardingSessionRecord) {
      $this->entityManager->remove($record);
      $this->entityManager->flush();
    }
  }

  /**
   * Method hydrateRecord.
   *
   * Applies all mutable fields from a mapped record onto an existing managed record.
   *
   * @since 1.0.0
   *
   * @param OrganizationOnboardingSessionRecord $existing the managed Doctrine entity
   * @param OrganizationOnboardingSessionRecord $record the freshly mapped source record
   */
  private function hydrateRecord(OrganizationOnboardingSessionRecord $existing, OrganizationOnboardingSessionRecord $record): void
  {
    $existing->flow = $record->flow;
    $existing->state = $record->state;
    $existing->nextStep = $record->nextStep;
    $existing->blockedReason = $record->blockedReason;
    $existing->targetOrganizationId = $record->targetOrganizationId;
    $existing->targetOrganizationName = $record->targetOrganizationName;
    $existing->completedSteps = $record->completedSteps;
    $existing->skippedSteps = $record->skippedSteps;
    $existing->rollbackStack = $record->rollbackStack;
    $existing->stepHistory = $record->stepHistory;
    $existing->updatedAt = $record->updatedAt;
  }
  // #endregion
}
