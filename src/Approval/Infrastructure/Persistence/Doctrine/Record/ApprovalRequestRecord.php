<?php

declare(strict_types=1);

namespace Approval\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Record ApprovalRequestRecord.
 *
 * `organization_id` is a plain column (not an ORM association) with no
 * foreign key, mirroring `Automation\Infrastructure\Persistence\Doctrine\Record\AutomationRunRecord`'s
 * precedent: Approval never depends on the Organization ORM mapping.
 *
 * @category Record
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ORM\Entity]
#[ORM\Table(name: 'approval_requests')]
#[ORM\Index(name: 'idx_approval_request_org_status', columns: ['organization_id', 'status'])]
#[ORM\Index(name: 'idx_approval_request_org_action_subject', columns: ['organization_id', 'action_type', 'subject_id'])]
#[ORM\Index(name: 'idx_approval_request_status_expires', columns: ['status', 'expires_at'])]
class ApprovalRequestRecord
{
  // #region Properties
  /**
   * Property id.
   *
   * @since 1.0.0
   */
  #[ORM\Id]
  #[ORM\Column(type: 'string', length: 36)]
  public string $id;

  /**
   * Property organizationId.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'organization_id', type: 'string', length: 36)]
  public string $organizationId;

  /**
   * Property actionType.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'action_type', type: 'string', length: 64)]
  public string $actionType;

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
  #[ORM\Column(name: 'status', type: 'string', length: 16)]
  public string $status;

  /**
   * Property requestedByMemberId.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'requested_by_member_id', type: 'string', length: 36)]
  public string $requestedByMemberId;

  /**
   * Property requestedByUserId.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'requested_by_user_id', type: 'string', length: 36)]
  public string $requestedByUserId;

  /**
   * Property decisionByMemberId.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'decision_by_member_id', type: 'string', length: 36, nullable: true)]
  public ?string $decisionByMemberId = null;

  /**
   * Property decisionByUserId.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'decision_by_user_id', type: 'string', length: 36, nullable: true)]
  public ?string $decisionByUserId = null;

  /**
   * Property decisionNote.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'decision_note', type: 'text', nullable: true)]
  public ?string $decisionNote = null;

  /**
   * Property payload.
   *
   * @since 1.0.0
   *
   * @var array<string, mixed>
   */
  #[ORM\Column(name: 'payload', type: 'json')]
  public array $payload = [];

  /**
   * Property expiresAt.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'expires_at', type: 'datetime_immutable')]
  public DateTimeImmutable $expiresAt;

  /**
   * Property createdAt.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
  public DateTimeImmutable $createdAt;

  /**
   * Property updatedAt.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
  public DateTimeImmutable $updatedAt;

  /**
   * Property decidedAt.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'decided_at', type: 'datetime_immutable', nullable: true)]
  public ?DateTimeImmutable $decidedAt = null;

  /**
   * Property executedAt.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'executed_at', type: 'datetime_immutable', nullable: true)]
  public ?DateTimeImmutable $executedAt = null;

  /**
   * Property executionError.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'execution_error', type: 'text', nullable: true)]
  public ?string $executionError = null;
  // #endregion
}
