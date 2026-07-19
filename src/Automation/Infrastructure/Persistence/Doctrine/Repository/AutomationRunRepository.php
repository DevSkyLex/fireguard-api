<?php

declare(strict_types=1);

namespace Automation\Infrastructure\Persistence\Doctrine\Repository;

use Automation\Application\Port\Outbound\AutomationRunPort;
use Automation\Domain\Exception\AutomationRunNotFoundException;
use Automation\Infrastructure\Persistence\Doctrine\Record\AutomationRunRecord;
use DateTimeImmutable;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Shared\Application\Factory\UuidFactory;

/**
 * Repository AutomationRunRepository.
 *
 * @category Repository
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AutomationRunRepository implements AutomationRunPort
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager the entity manager value
   * @param UuidFactory $uuidFactory the uuid factory value
   */
  public function __construct(
    private EntityManagerInterface $entityManager,
    private UuidFactory $uuidFactory,
  ) {
  }
  // #endregion

  // #region Methods
  public function reserveRun(string $ruleKey, string $organizationId, string $subjectId): ?string
  {
    $runId = $this->uuidFactory->generateRaw();

    // A raw DBAL statement — not the ORM's persist()/flush() — is used
    // deliberately: a unique-constraint violation during an ORM flush()
    // closes the EntityManager (see
    // `Intervention\Infrastructure\Adapter\Recurrence\DoctrineInterventionRecurrenceAdapter::reserveRun()`
    // for the same concern and precedent). A duplicate claim is an
    // expected, routine outcome here, not an exceptional one.
    try {
      $this->entityManager->getConnection()->executeStatement(
        'INSERT INTO automation_runs (id, rule_key, organization_id, subject_id, status, error, created_at) '
        . 'VALUES (:id, :ruleKey, :organizationId, :subjectId, :status, :error, :createdAt)',
        [
          'id' => $runId,
          'ruleKey' => $ruleKey,
          'organizationId' => $organizationId,
          'subjectId' => $subjectId,
          'status' => 'failed',
          'error' => 'Automation run reserved but not yet completed.',
          'createdAt' => new DateTimeImmutable(),
        ],
        ['createdAt' => 'datetime_immutable'],
      );
    } catch (UniqueConstraintViolationException) {
      return null;
    }

    return $runId;
  }

  public function markSkipped(string $runId): void
  {
    $this->updateStatus($runId, 'skipped');
  }

  public function markSucceeded(string $runId, string $interventionId): void
  {
    $record = $this->find($runId);
    $record->status = 'succeeded';
    $record->interventionId = $interventionId;
    $record->error = null;
    $this->entityManager->flush();
  }

  public function markFailed(string $runId, string $error): void
  {
    $record = $this->find($runId);
    $record->status = 'failed';
    $record->error = $error;
    $this->entityManager->flush();
  }

  /**
   * Method updateStatus.
   *
   * @since 1.0.0
   *
   * @param string $runId the run id value
   * @param string $status the status value
   */
  private function updateStatus(string $runId, string $status): void
  {
    $record = $this->find($runId);
    $record->status = $status;
    $record->error = null;
    $this->entityManager->flush();
  }

  /**
   * Method find.
   *
   * @since 1.0.0
   *
   * @param string $runId the run id value
   *
   * @return AutomationRunRecord the run record
   */
  private function find(string $runId): AutomationRunRecord
  {
    $record = $this->entityManager->find(AutomationRunRecord::class, $runId);
    if (!$record instanceof AutomationRunRecord) {
      throw AutomationRunNotFoundException::withId($runId);
    }

    return $record;
  }
  // #endregion
}
