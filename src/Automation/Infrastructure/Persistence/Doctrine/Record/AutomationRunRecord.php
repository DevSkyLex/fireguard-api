<?php

declare(strict_types=1);

namespace Automation\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Record AutomationRunRecord.
 *
 * One execution attempt of an automation rule against a subject. The unique
 * `(rule_key, subject_id)` constraint is the idempotence guard: a Messenger
 * retry or a duplicate trigger dispatch can never execute the same rule
 * against the same subject twice. `organization_id` is denormalized (not a
 * foreign key) — this is a lightweight run-log table, decoupled from the
 * Organization module the same way `intervention_recurrence_runs` is
 * decoupled from `organizations` (only its parent `recurrence_id` carries a
 * foreign key).
 *
 * @category Record
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ORM\Entity]
#[ORM\Table(name: 'automation_runs')]
#[ORM\Index(name: 'idx_automation_run_organization', columns: ['organization_id'])]
#[ORM\UniqueConstraint(name: 'uniq_automation_run_rule_subject', columns: ['rule_key', 'subject_id'])]
class AutomationRunRecord
{
  /**
   * Property id.
   *
   * @since 1.0.0
   */
  #[ORM\Id]
  #[ORM\Column(type: 'string', length: 36)]
  public string $id;

  /**
   * Property ruleKey.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'rule_key', type: 'string', length: 80)]
  public string $ruleKey;

  /**
   * Property organizationId.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'organization_id', type: 'string', length: 36)]
  public string $organizationId;

  /**
   * Property subjectId.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'subject_id', type: 'string', length: 36)]
  public string $subjectId;

  /**
   * Property status.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'string', length: 16)]
  public string $status = 'failed';

  /**
   * Property interventionId.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'intervention_id', type: 'string', length: 36, nullable: true)]
  public ?string $interventionId = null;

  /**
   * Property error.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'text', nullable: true)]
  public ?string $error = null;

  /**
   * Property createdAt.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
  public DateTimeImmutable $createdAt;
}
