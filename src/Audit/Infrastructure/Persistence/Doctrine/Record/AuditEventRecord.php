<?php

declare(strict_types=1);

namespace Audit\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Record AuditEventRecord.
 *
 * @category Record
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ORM\Entity]
#[ORM\Table(name: 'audit_events')]
#[ORM\UniqueConstraint(name: 'uniq_audit_chain_sequence', columns: ['chain_id', 'sequence'])]
#[ORM\Index(name: 'idx_audit_occurred_at', columns: ['occurred_at'])]
#[ORM\Index(name: 'idx_audit_action', columns: ['action'])]
#[ORM\Index(name: 'idx_audit_actor_id', columns: ['actor_id'])]
#[ORM\Index(name: 'idx_audit_actor_email_hash', columns: ['actor_email_hash'])]
#[ORM\Index(name: 'idx_audit_subject_id', columns: ['subject_id'])]
#[ORM\Index(name: 'idx_audit_client_id', columns: ['client_id'])]
#[ORM\Index(name: 'idx_audit_tenant_id', columns: ['tenant_id'])]
#[ORM\Index(name: 'idx_audit_ip_hash', columns: ['ip_hash'])]
final class AuditEventRecord
{
  // #region Properties
  /**
   * Property id.
   *
   * The audit event ID.
   *
   * @since 1.0.0
   */
  #[ORM\Id]
  #[ORM\Column(type: UuidType::NAME, unique: true)]
  public Uuid $id;

  /**
   * Property chainId.
   *
   * The audit chain identifier.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'chain_id', type: 'string', length: 128)]
  public string $chainId;

  /**
   * Property sequence.
   *
   * The sequence number within the chain.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'sequence', type: 'bigint')]
  public int $sequence;

  /**
   * Property action.
   *
   * The audit action key.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'action', type: 'string', length: 120)]
  public string $action;

  /**
   * Property actorType.
   *
   * The actor type (user, client, system, anonymous).
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'actor_type', type: 'string', length: 32)]
  public string $actorType;

  /**
   * Property actorId.
   *
   * The actor identifier (if any).
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'actor_id', type: 'string', length: 64, nullable: true)]
  public ?string $actorId = null;

  /**
   * Property actorEmail.
   *
   * The actor email (optional, depending on PII settings).
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'actor_email', type: 'string', length: 255, nullable: true)]
  public ?string $actorEmail = null;

  /**
   * Property actorEmailHash.
   *
   * The hashed actor email.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'actor_email_hash', type: 'string', length: 64, nullable: true)]
  public ?string $actorEmailHash = null;

  /**
   * Property subjectType.
   *
   * The subject type (token, user, client, etc.).
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'subject_type', type: 'string', length: 32, nullable: true)]
  public ?string $subjectType = null;

  /**
   * Property subjectId.
   *
   * The subject identifier (if any).
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'subject_id', type: 'string', length: 64, nullable: true)]
  public ?string $subjectId = null;

  /**
   * Property clientId.
   *
   * The client identifier (if any).
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'client_id', type: 'string', length: 64, nullable: true)]
  public ?string $clientId = null;

  /**
   * Property tenantId.
   *
   * The tenant identifier (if any).
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'tenant_id', type: 'string', length: 64, nullable: true)]
  public ?string $tenantId = null;

  /**
   * Property ipAddress.
   *
   * The client IP address (optional).
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'ip_address', type: 'string', length: 45, nullable: true)]
  public ?string $ipAddress = null;

  /**
   * Property ipHash.
   *
   * The hashed client IP address.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'ip_hash', type: 'string', length: 64, nullable: true)]
  public ?string $ipHash = null;

  /**
   * Property userAgent.
   *
   * The user agent string (optional).
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'user_agent', type: 'string', length: 512, nullable: true)]
  public ?string $userAgent = null;

  /**
   * Property metadata.
   *
   * Additional metadata for the event.
   *
   * @since 1.0.0
   *
   * @var array<string, mixed>
   */
  #[ORM\Column(type: 'json')]
  public array $metadata = [];

  /**
   * Property occurredAt.
   *
   * The occurrence timestamp.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'occurred_at', type: 'datetime_immutable')]
  public DateTimeImmutable $occurredAt;

  /**
   * Property recordedAt.
   *
   * The record timestamp.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'recorded_at', type: 'datetime_immutable')]
  public DateTimeImmutable $recordedAt;

  /**
   * Property prevHash.
   *
   * The previous hash in the chain.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'prev_hash', type: 'string', length: 64, nullable: true)]
  public ?string $prevHash = null;

  /**
   * Property eventHash.
   *
   * The hash of the current event payload.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'event_hash', type: 'string', length: 64)]
  public string $eventHash;
  // #endregion
}
