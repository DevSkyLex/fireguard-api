<?php

declare(strict_types=1);

namespace Shared\Domain\Event;

use DateTimeImmutable;
use Shared\Domain\ValueObject\Uuid;

/**
 * Event AuditEvent.
 *
 * @category Event
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AuditEvent implements DomainEvent
{
  // #region Constants
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
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param Uuid                 $eventId    the event ID
   * @param string               $action     the action performed
   * @param string|null          $userId     the user ID (if applicable)
   * @param string|null          $clientId   the client ID (if applicable)
   * @param string               $ipAddress  the IP address
   * @param string               $userAgent  the user agent
   * @param array<string, mixed> $metadata   additional metadata
   * @param DateTimeImmutable    $occurredAt when the event occurred
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
  // #endregion

  // #region Factory Methods
  /**
   * Method loginSuccess.
   *
   * @static
   *
   * Creates a login success audit event.
   *
   * @since 1.0.0
   *
   * @param Uuid   $eventId   the event ID
   * @param string $userId    the user ID
   * @param string $ipAddress the IP address
   * @param string $userAgent the user agent
   *
   * @return self the audit event
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
   * Method loginFailed.
   *
   * @static
   *
   * Creates a login failed audit event.
   *
   * @since 1.0.0
   *
   * @param Uuid   $eventId           the event ID
   * @param string $attemptedUsername the attempted username
   * @param string $ipAddress         the IP address
   * @param string $userAgent         the user agent
   * @param string $reason            the failure reason
   *
   * @return self the audit event
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
   * Method tokenIssued.
   *
   * @static
   *
   * Creates a token issued audit event.
   *
   * @since 1.0.0
   *
   * @param Uuid        $eventId   the event ID
   * @param string      $clientId  the client ID
   * @param string|null $userId    the user ID (if applicable)
   * @param string      $grantType the grant type used
   * @param string      $ipAddress the IP address
   *
   * @return self the audit event
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
  // #endregion

  // #region Methods
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
   * Method action.
   *
   * @since 1.0.0
   *
   * @return string the action
   */
  public function action(): string
  {
    return $this->action;
  }

  /**
   * Method userId.
   *
   * @since 1.0.0
   *
   * @return string|null the user ID
   */
  public function userId(): ?string
  {
    return $this->userId;
  }

  /**
   * Method clientId.
   *
   * @since 1.0.0
   *
   * @return string|null the client ID
   */
  public function clientId(): ?string
  {
    return $this->clientId;
  }

  /**
   * Method ipAddress.
   *
   * @since 1.0.0
   *
   * @return string the IP address
   */
  public function ipAddress(): string
  {
    return $this->ipAddress;
  }

  /**
   * Method userAgent.
   *
   * @since 1.0.0
   *
   * @return string the user agent
   */
  public function userAgent(): string
  {
    return $this->userAgent;
  }

  /**
   * Method metadata.
   *
   * @since 1.0.0
   *
   * @return array<string, mixed> the metadata
   */
  public function metadata(): array
  {
    return $this->metadata;
  }
  // #endregion
}
