<?php

declare(strict_types=1);

namespace Automation\Infrastructure\Persistence\Doctrine\Repository;

use Automation\Application\Contract\Run\{AutomationAttemptView, AutomationRetry};
use Automation\Application\Port\Outbound\{AutomationRunHistoryPort, AutomationRunPort};
use Automation\Domain\Exception\{AutomationRetryNotAllowedException, AutomationRunNotFoundException};
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Shared\Application\Factory\UuidFactory;

use function array_map;
use function is_string;
use function json_decode;
use function json_encode;

use const JSON_THROW_ON_ERROR;

/** Repository AutomationRunRepository. One unique action with multiple fenced, retained attempts. */
final readonly class AutomationRunRepository implements AutomationRunPort, AutomationRunHistoryPort
{
  public function __construct(private EntityManagerInterface $entityManager, private UuidFactory $uuidFactory)
  {
  }

  public function reserveRun(string $ruleKey, string $organizationId, string $subjectId, array $triggerPayload = [], ?string $attemptId = null): ?string
  {
    $db = $this->entityManager->getConnection();
    if (null !== $attemptId) {
      $runId = $db->fetchOne("UPDATE automation_runs SET status = 'running' WHERE rule_key = ? AND organization_id = ? AND subject_id = ? AND current_attempt_id = ? AND status = 'pending' RETURNING id", [$ruleKey, $organizationId, $subjectId, $attemptId]);
      if (!is_string($runId)) {
        return null;
      }
      $db->executeStatement("UPDATE automation_attempts SET status = 'running' WHERE id = ?", [$attemptId]);

      return $runId;
    }
    $runId = $this->uuidFactory->generateRaw();
    $now = new DateTimeImmutable();
    $inserted = $db->executeStatement(
      'INSERT INTO automation_runs (id, rule_key, organization_id, subject_id, status, error, created_at, trigger_payload, current_attempt_id, attempt_count) VALUES (:id, :rule, :org, :subject, :status, :error, :created, :payload, :attempt, 1) ON CONFLICT DO NOTHING',
      ['id' => $runId, 'rule' => $ruleKey, 'org' => $organizationId, 'subject' => $subjectId, 'status' => 'failed', 'error' => 'Automation run reserved but not yet completed.', 'created' => $now, 'payload' => json_encode($triggerPayload, JSON_THROW_ON_ERROR), 'attempt' => $runId],
      ['created' => 'datetime_immutable'],
    );
    if (0 === $inserted) {
      return null;
    }
    $db->executeStatement('INSERT INTO automation_attempts (id, run_id, attempt_number, status, created_at) VALUES (?, ?, 1, ?, ?)', [$runId, $runId, 'running', $now], [3 => 'datetime_immutable']);

    return $runId;
  }

  public function markSkipped(string $runId): void
  {
    $this->settle($runId, 'skipped');
  }

  public function markSucceeded(string $runId, string $interventionId): void
  {
    $this->settle($runId, 'succeeded', null, $interventionId);
  }

  public function markFailed(string $runId, string $error): void
  {
    $this->settle($runId, 'failed', $error);
  }

  public function reserveRetry(string $organizationId, string $runId, string $attemptId, string $actorUserId): AutomationRetry
  {
    $db = $this->entityManager->getConnection();
    $row = $db->fetchAssociative('SELECT * FROM automation_runs WHERE id = ? AND organization_id = ? FOR UPDATE', [$runId, $organizationId]);
    if (false === $row) {
      throw AutomationRunNotFoundException::withId($runId);
    }
    if ($row['current_attempt_id'] !== $attemptId || 'failed' !== $row['status'] || !is_string($row['trigger_payload'])) {
      throw new AutomationRetryNotAllowedException('Only the current failed attempt with retained inputs can be retried.');
    }
    /** @var array<string, mixed> $payload */
    $payload = json_decode($row['trigger_payload'], true, 512, JSON_THROW_ON_ERROR);
    $id = $this->uuidFactory->generateRaw();
    $now = new DateTimeImmutable();
    $db->executeStatement("UPDATE automation_runs SET status = 'pending', error = NULL, current_attempt_id = ?, attempt_count = attempt_count + 1 WHERE id = ?", [$id, $runId]);
    $db->executeStatement("INSERT INTO automation_attempts (id, run_id, attempt_number, status, requested_by, created_at) SELECT ?, id, attempt_count, 'pending', ?, ? FROM automation_runs WHERE id = ?", [$id, $actorUserId, $now, $runId], [2 => 'datetime_immutable']);
    /** @var array<string, mixed> $attempt */
    $attempt = $db->fetchAssociative('SELECT a.*, r.rule_key, r.organization_id, r.subject_id, r.current_attempt_id, r.trigger_payload, r.intervention_id FROM automation_attempts a JOIN automation_runs r ON r.id = a.run_id WHERE a.id = ?', [$id]);

    return new AutomationRetry($this->view($attempt, false), $payload);
  }

  public function listAttempts(string $organizationId, int $limit, int $offset, bool $canRetry): array
  {
    $rows = $this->entityManager->getConnection()->fetchAllAssociative('SELECT a.*, r.rule_key, r.organization_id, r.subject_id, r.current_attempt_id, r.trigger_payload, r.intervention_id FROM automation_attempts a JOIN automation_runs r ON r.id = a.run_id WHERE r.organization_id = :org ORDER BY a.created_at DESC, a.attempt_number DESC, a.id DESC LIMIT :lim OFFSET :off', ['org' => $organizationId, 'lim' => $limit, 'off' => $offset], ['lim' => \Doctrine\DBAL\ParameterType::INTEGER, 'off' => \Doctrine\DBAL\ParameterType::INTEGER]);

    return array_map(fn (array $row): AutomationAttemptView => $this->view($row, $canRetry), $rows);
  }

  public function getAttempt(string $organizationId, string $attemptId, bool $canRetry): AutomationAttemptView
  {
    $row = $this->entityManager->getConnection()->fetchAssociative('SELECT a.*, r.rule_key, r.organization_id, r.subject_id, r.current_attempt_id, r.trigger_payload, r.intervention_id FROM automation_attempts a JOIN automation_runs r ON r.id = a.run_id WHERE r.organization_id = ? AND a.id = ?', [$organizationId, $attemptId]);
    if (false === $row) {
      throw AutomationRunNotFoundException::withId($attemptId);
    }

    return $this->view($row, $canRetry);
  }

  public function countAttempts(string $organizationId): int
  {
    /** @var int|string $count */
    $count = $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM automation_attempts a JOIN automation_runs r ON r.id = a.run_id WHERE r.organization_id = ?', [$organizationId]);

    return (int) $count;
  }

  private function settle(string $runId, string $status, ?string $error = null, ?string $interventionId = null): void
  {
    $db = $this->entityManager->getConnection();
    $attempt = $db->fetchOne('UPDATE automation_runs SET status = ?, error = ?, intervention_id = ? WHERE id = ? RETURNING current_attempt_id', [$status, $error, $interventionId, $runId]);
    if (false === $attempt) {
      throw AutomationRunNotFoundException::withId($runId);
    }
    $db->executeStatement('UPDATE automation_attempts SET status = ?, finished_at = ? WHERE id = ?', [$status, new DateTimeImmutable(), $attempt], [1 => 'datetime_immutable']);
  }

  /**
   * @param array<string, mixed> $row
   */
  private function view(array $row, bool $canRetry): AutomationAttemptView
  {
    /** @var array{id:string, run_id:string, organization_id:string, rule_key:string, subject_id:string, attempt_number:int, status:string, created_at:string, finished_at:?string, requested_by:?string, intervention_id:?string, current_attempt_id:?string, trigger_payload:?string} $row */
    return new AutomationAttemptView($row['id'], $row['run_id'], $row['organization_id'], $row['rule_key'], $row['subject_id'], $row['attempt_number'], $row['status'], new DateTimeImmutable($row['created_at'])->format('c'), null === $row['finished_at'] ? null : new DateTimeImmutable($row['finished_at'])->format('c'), $row['requested_by'], 'succeeded' === $row['status'] ? $row['intervention_id'] : null, 'failed' === $row['status'] ? 'automation_action_failed' : null, $canRetry && 'failed' === $row['status'] && $row['current_attempt_id'] === $row['id'] && null !== $row['trigger_payload']);
  }
}
