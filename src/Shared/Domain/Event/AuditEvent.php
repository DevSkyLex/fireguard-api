<?php

declare(strict_types=1);

namespace Shared\Domain\Event;

use DateTimeImmutable;
use Shared\Domain\ValueObject\Uuid;

/**
 * Event AuditEvent
 * @final
 *
 * Generic audit event for security-relevant actions.
 *
 * @category Event
 * @package Shared\Domain\Event
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AuditEvent implements DomainEvent
{
  //#region Constants
  public const string ACTION_LOGIN_SUCCESS = 'login_success';
  public const string ACTION_LOGIN_FAILED = 'login_failed';
  public const string ACTION_LOGOUT = 'logout';
  public const string ACTION_TOKEN_ISSUED = 'token_issued';
  public const string ACTION_TOKEN_REVOKED = 'token_revoked';
  public const string ACTION_TOKEN_REFRESHED = 'token_refreshed';
  public const string ACTION_PASSWORD_CHANGED = 'password_changed';
  public const string ACTION_PASSWORD_RESET_REQUESTED = 'password_reset_requested';
  public const string ACTION_CLIENT_CREATED = 'client_created';
  public const string ACTION_CLIENT_DELETED = 'client_deleted';
  public const string ACTION_USER_CREATED = 'user_created';
  public const string ACTION_PERMISSION_DENIED = 'permission_denied';
  //#endregion

  //#region Constructor
  /**
   * Constructor
   *
   * @access public
   * @since 1.0.0
   *
   * @param Uuid $eventId The event ID.
   * @param string $action The action performed.
   * @param string|null $userId The user ID (if applicable).
   * @param string|null $clientId The client ID (if applicable).
   * @param string $ipAddress The IP address.
   * @param string $userAgent The user agent.
   * @param array<string, mixed> $metadata Additional metadata.
   * @param DateTimeImmutable $occurredAt When the event occurred.
   */
  public function __construct(
    private Uuid $eventId,
    private string $action,
    private ?string $userId,
    private ?string $clientId,
    private string $ipAddress,
    private string $userAgent,
    private array $metadata,
    private DateTimeImmutable $occurredAt,
  ) {
  }
  //#endregion

  //#region Factory Methods
  /**
   * Method loginSuccess
   * @static
   *
   * Creates a login success audit event.
   *
   * @access public
   * @since 1.0.0
   *
   * @param Uuid $eventId The event ID.
   * @param string $userId The user ID.
   * @param string $ipAddress The IP address.
   * @param string $userAgent The user agent.
   *
   * @return self The audit event.
   */
  public static function loginSuccess(
    Uuid $eventId,
    string $userId,
    string $ipAddress,
    string $userAgent,
  ): self {
    return new self(
      eventId: $eventId,
      action: self::ACTION_LOGIN_SUCCESS,
      userId: $userId,
      clientId: null,
      ipAddress: $ipAddress,
      userAgent: $userAgent,
      metadata: [],
      occurredAt: new DateTimeImmutable(),
    );
  }

  /**
   * Method loginFailed
   * @static
   *
   * Creates a login failed audit event.
   *
   * @access public
   * @since 1.0.0
   *
   * @param Uuid $eventId The event ID.
   * @param string $attemptedUsername The attempted username.
   * @param string $ipAddress The IP address.
   * @param string $userAgent The user agent.
   * @param string $reason The failure reason.
   *
   * @return self The audit event.
   */
  public static function loginFailed(
    Uuid $eventId,
    string $attemptedUsername,
    string $ipAddress,
    string $userAgent,
    string $reason,
  ): self {
    return new self(
      eventId: $eventId,
      action: self::ACTION_LOGIN_FAILED,
      userId: null,
      clientId: null,
      ipAddress: $ipAddress,
      userAgent: $userAgent,
      metadata: [
        'attempted_username' => $attemptedUsername,
        'failure_reason' => $reason,
      ],
      occurredAt: new DateTimeImmutable(),
    );
  }

  /**
   * Method tokenIssued
   * @static
   *
   * Creates a token issued audit event.
   *
   * @access public
   * @since 1.0.0
   *
   * @param Uuid $eventId The event ID.
   * @param string $clientId The client ID.
   * @param string|null $userId The user ID (if applicable).
   * @param string $grantType The grant type used.
   * @param string $ipAddress The IP address.
   *
   * @return self The audit event.
   */
  public static function tokenIssued(
    Uuid $eventId,
    string $clientId,
    ?string $userId,
    string $grantType,
    string $ipAddress,
  ): self {
    return new self(
      eventId: $eventId,
      action: self::ACTION_TOKEN_ISSUED,
      userId: $userId,
      clientId: $clientId,
      ipAddress: $ipAddress,
      userAgent: '',
      metadata: [
        'grant_type' => $grantType,
      ],
      occurredAt: new DateTimeImmutable(),
    );
  }
  //#endregion

  //#region Methods
  /**
   * Method eventId
   * {@inheritDoc}
   */
  public function eventId(): Uuid
  {
    return $this->eventId;
  }

  /**
   * Method occurredAt
   * {@inheritDoc}
   */
  public function occurredAt(): DateTimeImmutable
  {
    return $this->occurredAt;
  }

  /**
   * Method aggregateId
   * {@inheritDoc}
   */
  public function aggregateId(): string
  {
    return $this->userId ?? $this->clientId ?? 'system';
  }

  /**
   * Method aggregateType
   * {@inheritDoc}
   */
  public function aggregateType(): string
  {
    return 'Audit';
  }

  /**
   * Method payload
   * {@inheritDoc}
   *
   * @return array<string, mixed>
   */
  public function payload(): array
  {
    return [
      'action' => $this->action,
      'user_id' => $this->userId,
      'client_id' => $this->clientId,
      'ip_address' => $this->ipAddress,
      'user_agent' => $this->userAgent,
      'metadata' => $this->metadata,
    ];
  }

  /**
   * Method action
   *
   * @access public
   * @since 1.0.0
   *
   * @return string The action.
   */
  public function action(): string
  {
    return $this->action;
  }

  /**
   * Method userId
   *
   * @access public
   * @since 1.0.0
   *
   * @return string|null The user ID.
   */
  public function userId(): ?string
  {
    return $this->userId;
  }

  /**
   * Method clientId
   *
   * @access public
   * @since 1.0.0
   *
   * @return string|null The client ID.
   */
  public function clientId(): ?string
  {
    return $this->clientId;
  }

  /**
   * Method ipAddress
   *
   * @access public
   * @since 1.0.0
   *
   * @return string The IP address.
   */
  public function ipAddress(): string
  {
    return $this->ipAddress;
  }

  /**
   * Method userAgent
   *
   * @access public
   * @since 1.0.0
   *
   * @return string The user agent.
   */
  public function userAgent(): string
  {
    return $this->userAgent;
  }

  /**
   * Method metadata
   *
   * @access public
   * @since 1.0.0
   *
   * @return array<string, mixed> The metadata.
   */
  public function metadata(): array
  {
    return $this->metadata;
  }
  //#endregion
}
