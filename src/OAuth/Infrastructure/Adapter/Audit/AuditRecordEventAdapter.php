<?php

declare(strict_types=1);

namespace OAuth\Infrastructure\Adapter\Audit;

use Audit\Application\Contract\AuditRecordRequest;
use Audit\Application\Port\Inbound\AuditRecordingPort;
use LogicException;
use OAuth\Application\Contract\Event\Client\ClientLifecycleChangedEvent;
use OAuth\Application\Contract\Event\Consent\ConsentGrantedEvent;
use OAuth\Application\Contract\Event\Token\{TokenIssueFailedEvent, TokenIssuedEvent, TokenRefreshFailedEvent, TokenRefreshedEvent, TokenRevokedEvent};
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class AuditRecordEventAdapter
 *
 * Projects OAuth-owned event facts through the published Audit recording capability. Audit owns context, privacy and durable ledger delivery.
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
      'oauth.token_issued_event' => 'onOAuthTokenIssued',
      'oauth.token_issue_failed_event' => 'onOAuthTokenIssueFailed',
      'oauth.token_refreshed_event' => 'onOAuthTokenRefreshed',
      'oauth.token_refresh_failed_event' => 'onOAuthTokenRefreshFailed',
      'oauth.token_revoked_event' => 'onOAuthTokenRevoked',
      'oauth.consent_granted_event' => 'onConsentGranted',
      'oauth.client_lifecycle_changed_event' => 'onClientLifecycleChanged',
    ];
  }

  /**
   * Records owner-published client mutations without copying credentials or freeform client metadata.
   *
   * @param ClientLifecycleChangedEvent $event immutable client mutation fact
   *
   * @return void
   */
  public function onClientLifecycleChanged(ClientLifecycleChangedEvent $event): void
  {
    $action = match ($event->eventType) {
      'oauth.client.registered' => 'oauth.client_registered',
      'oauth.client.updated' => 'oauth.client_updated',
      'oauth.client.secret_regenerated' => 'oauth.client_secret_regenerated',
      'oauth.client.activated' => 'oauth.client_activated',
      'oauth.client.deactivated' => 'oauth.client_deactivated',
      'oauth.client.deleted' => 'oauth.client_deleted',
      default => throw new LogicException('Unsupported published OAuth client lifecycle fact.'),
    };
    $this->audit->requestRecord(new AuditRecordRequest(action: $action, subjectType: 'oauth_client', subjectId: $event->clientId, clientId: $event->clientId, metadata: ['owner_event_id' => $event->eventId], occurredAt: $event->occurredAt));
  }

  /**
   * Method onOAuthTokenIssued.
   *
   * Records a token issued event from the OAuth module.
   *
   * @since 1.0.0
   *
   * @param TokenIssuedEvent $event the domain event
   */
  public function onOAuthTokenIssued(TokenIssuedEvent $event): void
  {
    $ip = $event->ipAddress;

    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'oauth.token_issued',
      actorType: 'client',
      actorId: $event->clientId,
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

  /**
   * Method onOAuthTokenIssueFailed.
   *
   * Records a token issuance failure.
   *
   * @since 1.0.0
   *
   * @param TokenIssueFailedEvent $event the domain event
   */
  public function onOAuthTokenIssueFailed(TokenIssueFailedEvent $event): void
  {
    $ip = $event->ipAddress;

    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'oauth.token_issue_failed',
      actorType: 'client',
      actorId: $event->clientId,
      clientId: $event->clientId,
      ipAddress: $ip,
      metadata: [
        'grant_type' => $event->grantType,
        'reason' => $event->reason,
      ],
      occurredAt: $event->occurredAt,
    ));
  }

  /**
   * Method onOAuthTokenRefreshed.
   *
   * Records a successful token refresh.
   *
   * @since 1.0.0
   *
   * @param TokenRefreshedEvent $event the domain event
   */
  public function onOAuthTokenRefreshed(TokenRefreshedEvent $event): void
  {
    $ip = $event->ipAddress;

    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'oauth.token_refreshed',
      actorType: 'user',
      actorId: $event->userId,
      ipAddress: $ip,
      metadata: [],
      occurredAt: $event->occurredAt,
    ));
  }

  /**
   * Method onOAuthTokenRefreshFailed.
   *
   * Records a failed token refresh.
   *
   * @since 1.0.0
   *
   * @param TokenRefreshFailedEvent $event the domain event
   */
  public function onOAuthTokenRefreshFailed(TokenRefreshFailedEvent $event): void
  {
    $ip = $event->ipAddress;

    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'oauth.token_refresh_failed',
      actorType: 'user',
      actorId: $event->userId,
      ipAddress: $ip,
      metadata: [
        'reason' => $event->reason,
      ],
      occurredAt: $event->occurredAt,
    ));
  }

  /**
   * Method onOAuthTokenRevoked.
   *
   * Records a token revocation event.
   *
   * @since 1.0.0
   *
   * @param TokenRevokedEvent $event the domain event
   */
  public function onOAuthTokenRevoked(TokenRevokedEvent $event): void
  {
    $ip = $event->ipAddress;

    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'oauth.token_revoked',
      actorType: $event->clientId ? 'client' : 'system',
      actorId: $event->clientId,
      subjectType: 'token',
      subjectId: $event->tokenId,
      clientId: $event->clientId,
      ipAddress: $ip,
      metadata: [
        'token_type' => $event->tokenType,
        'reason' => $event->reason,
        'user_id' => $event->userId,
      ],
      occurredAt: $event->occurredAt,
    ));
  }

  /**
   * Method onConsentGranted.
   *
   * Records a consent grant/update event.
   *
   * @since 1.0.0
   *
   * @param ConsentGrantedEvent $event the domain event
   */
  public function onConsentGranted(ConsentGrantedEvent $event): void
  {
    $action = $event->isNew ? 'oauth.consent_granted' : 'oauth.consent_updated';

    $this->audit->requestRecord(new AuditRecordRequest(
      action: $action,
      actorType: 'user',
      actorId: $event->userId,
      subjectType: 'client',
      subjectId: $event->clientId,
      clientId: $event->clientId,
      metadata: [
        'scopes' => $event->scopes,
        'is_new' => $event->isNew,
      ],
      occurredAt: $event->occurredAt,
    ));
  }
  // #endregion
}
