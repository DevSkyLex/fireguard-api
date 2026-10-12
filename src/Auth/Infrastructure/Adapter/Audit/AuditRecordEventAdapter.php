<?php

declare(strict_types=1);

namespace Auth\Infrastructure\Adapter\Audit;

use Audit\Application\Contract\AuditRecordRequest;
use Audit\Application\Port\Inbound\AuditRecordingPort;
use Auth\Application\Contract\Event\Session\{LoginFailedEvent, UserLoggedInEvent, UserLoggedOutEvent};
use Auth\Application\Contract\Event\Token\TokenIssuedEvent as AuthTokenIssuedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class AuditRecordEventAdapter
 *
 * Projects Auth-owned event facts through the published Audit recording capability. Audit owns context, privacy and durable ledger delivery.
 *
 * @category Adapter
 */
final readonly class AuditRecordEventAdapter implements EventSubscriberInterface
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Receives the published durable recording seam.
   *
   * @access public
   *
   * @param AuditRecordingPort $audit generic Audit write capability
   *
   * @return void
   */
  public function __construct(private AuditRecordingPort $audit)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method getSubscribedEvents
   *
   * Preserves the existing owner event names and audit action mapping.
   *
   * @access public
   *
   * @return array<string, string> owner event listeners
   */
  public static function getSubscribedEvents(): array
  {
    return [
      'auth.user_logged_in_event' => 'onUserLoggedIn',
      'auth.login_failed_event' => 'onLoginFailed',
      'auth.user_logged_out_event' => 'onUserLoggedOut',
      'auth.token_issued_event' => 'onAuthTokenIssued',
    ];
  }

  /**
   * Method onUserLoggedIn.
   *
   * Records a successful login audit event.
   *
   * @since 1.0.0
   *
   * @param UserLoggedInEvent $event the domain event
   */
  public function onUserLoggedIn(UserLoggedInEvent $event): void
  {
    $ip = $event->ipAddress;

    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'auth.login_success',
      actorType: 'user',
      actorId: $event->userId,
      actorEmail: $event->email,
      ipAddress: $ip,
      metadata: [],
      occurredAt: $event->occurredAt,
    ));
  }

  /**
   * Method onLoginFailed.
   *
   * Records a failed login audit event.
   *
   * @since 1.0.0
   *
   * @param LoginFailedEvent $event the domain event
   */
  public function onLoginFailed(LoginFailedEvent $event): void
  {
    $ip = $event->ipAddress;

    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'auth.login_failed',
      actorType: 'anonymous',
      actorId: null,
      actorEmail: $event->email,
      ipAddress: $ip,
      metadata: [
        'reason' => $event->reason,
      ],
      occurredAt: $event->occurredAt,
    ));
  }

  /**
   * Method onUserLoggedOut.
   *
   * Records a logout audit event.
   *
   * @since 1.0.0
   *
   * @param UserLoggedOutEvent $event the domain event
   */
  public function onUserLoggedOut(UserLoggedOutEvent $event): void
  {
    $ip = $event->ipAddress;

    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'auth.logout',
      actorType: $event->userId ? 'user' : 'anonymous',
      actorId: $event->userId,
      ipAddress: $ip,
      metadata: [
        'refresh_token_revoked' => $event->refreshTokenRevoked,
        'access_token_revoked' => $event->accessTokenRevoked,
      ],
      occurredAt: $event->occurredAt,
    ));
  }

  /**
   * Method onAuthTokenIssued.
   *
   * Records a token issued event from the Auth module.
   *
   * @since 1.0.0
   *
   * @param AuthTokenIssuedEvent $event the domain event
   */
  public function onAuthTokenIssued(AuthTokenIssuedEvent $event): void
  {
    $ip = $event->ipAddress;
    $actorType = $event->userId ? 'user' : 'client';
    $actorId = $event->userId ?? $event->clientId;

    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'auth.token_issued',
      actorType: $actorType,
      actorId: $actorId,
      subjectType: 'token',
      subjectId: $event->tokenId,
      clientId: $event->clientId,
      ipAddress: $ip,
      metadata: [
        'grant_type' => $event->grantType,
        'scopes' => $event->scopes,
        'expires_in' => $event->expiresIn,
        'user_id' => $event->userId,
      ],
      occurredAt: $event->occurredAt,
    ));
  }
  // #endregion
}
