<?php

declare(strict_types=1);

namespace Automation\Application\Port\Outbound;

/**
 * Interface AutomationRunPort.
 *
 * Persists automation rule execution attempts and backs the idempotence
 * guard: one run row per `(rule_key, subject_id)` pair, enforced by a
 * unique constraint on `automation_runs` so a Messenger retry or a
 * duplicate trigger dispatch can never execute the same rule against the
 * same subject twice.
 *
 * @category Interface
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface AutomationRunPort
{
  /**
   * Method reserveRun.
   *
   * Idempotently claims a `(rule_key, subject_id)` pair by inserting a
   * placeholder run row, immediately updated to its final outcome by
   * {@see self::markSkipped()}, {@see self::markSucceeded()} or
   * {@see self::markFailed()}. Returns `null` when the pair is already
   * claimed (a previous attempt already executed or is executing this rule
   * for this subject), so the caller must skip execution entirely rather
   * than reprocess it.
   *
   * @since 1.0.0
   *
   * @param string $ruleKey the automation rule key
   * @param string $organizationId the organization identifier
   * @param string $subjectId the subject identifier the rule runs against
   *
   * @return ?string the reserved run id, or null when already claimed
   */
  public function reserveRun(string $ruleKey, string $organizationId, string $subjectId): ?string;

  /**
   * Method markSkipped.
   *
   * @since 1.0.0
   *
   * @param string $runId the run id value
   */
  public function markSkipped(string $runId): void;

  /**
   * Method markSucceeded.
   *
   * @since 1.0.0
   *
   * @param string $runId the run id value
   * @param string $interventionId the created corrective intervention identifier
   */
  public function markSucceeded(string $runId, string $interventionId): void;

  /**
   * Method markFailed.
   *
   * @since 1.0.0
   *
   * @param string $runId the run id value
   * @param string $error the failure reason value
   */
  public function markFailed(string $runId, string $error): void;
}
