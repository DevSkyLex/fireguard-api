<?php

declare(strict_types=1);

namespace Audit\Infrastructure\EventSubscriber;

use Audit\Application\UseCase\Command\RecordAuditEvent\RecordAuditEventCommand;
use Audit\Infrastructure\Service\AuditPiiSanitizer;
use Auth\Domain\Event\Session\{LoginFailedEvent, UserLoggedInEvent, UserLoggedOutEvent};
use Auth\Domain\Event\Token\TokenIssuedEvent as AuthTokenIssuedEvent;
use OAuth\Domain\Event\Consent\ConsentGrantedEvent;
use OAuth\Domain\Event\Token\{TokenIssueFailedEvent, TokenIssuedEvent, TokenRefreshFailedEvent, TokenRefreshedEvent, TokenRevokedEvent};
use Psr\Log\LoggerInterface;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Throwable;

/**
 * Subscriber AuditEventSubscriber.
 *
 * Records security-sensitive events
 * into the audit ledger.
 *
 * @category Subscriber
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AuditEventSubscriber implements EventSubscriberInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * AuditEventSubscriber class.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus the command bus
   * @param AuditPiiSanitizer $sanitizer the PII sanitizer
   * @param RequestStack $requestStack the request stack
   * @param LoggerInterface $logger the logger
   */
  public function __construct(
    private CommandBusPort $commandBus,
    private AuditPiiSanitizer $sanitizer,
    private RequestStack $requestStack,
    #[Autowire(service: 'monolog.logger.security')]
    private LoggerInterface $logger,
  ) {
  }
  // #endregion

  /**
   * Method getSubscribedEvents.
   *
   * Returns the event map for the subscriber.
   *
   * @since 1.0.0
   *
   * @return array<string, string>
   */
  public static function getSubscribedEvents(): array
  {
    return [
      'auth.user_logged_in_event' => 'onUserLoggedIn',
      'auth.login_failed_event' => 'onLoginFailed',
      'auth.user_logged_out_event' => 'onUserLoggedOut',
      'auth.token_issued_event' => 'onAuthTokenIssued',
      'oauth.token_issued_event' => 'onOAuthTokenIssued',
      'oauth.token_issue_failed_event' => 'onOAuthTokenIssueFailed',
      'oauth.token_refreshed_event' => 'onOAuthTokenRefreshed',
      'oauth.token_refresh_failed_event' => 'onOAuthTokenRefreshFailed',
      'oauth.token_revoked_event' => 'onOAuthTokenRevoked',
      'oauth.consent_granted_event' => 'onConsentGranted',
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
    $context = $this->requestContext();
    $ip = $event->ipAddress ?? $context['ip'];

    $this->dispatchAuditEvent(new RecordAuditEventCommand(
      action: 'auth.login_success',
      actorType: 'user',
      actorId: $event->userId,
      actorEmail: $this->sanitizer->email($event->email),
      actorEmailHash: $this->sanitizer->emailHash($event->email),
      ipAddress: $this->sanitizer->ip($ip),
      ipHash: $this->sanitizer->ipHash($ip),
      userAgent: $context['user_agent'],
      metadata: $this->withRequestMeta([]),
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
    $context = $this->requestContext();
    $ip = $event->ipAddress ?? $context['ip'];

    $this->dispatchAuditEvent(new RecordAuditEventCommand(
      action: 'auth.login_failed',
      actorType: 'anonymous',
      actorId: null,
      actorEmail: $this->sanitizer->email($event->email),
      actorEmailHash: $this->sanitizer->emailHash($event->email),
      ipAddress: $this->sanitizer->ip($ip),
      ipHash: $this->sanitizer->ipHash($ip),
      userAgent: $context['user_agent'],
      metadata: $this->withRequestMeta([
        'reason' => $event->reason,
      ]),
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
    $context = $this->requestContext();
    $ip = $event->ipAddress ?? $context['ip'];

    $this->dispatchAuditEvent(new RecordAuditEventCommand(
      action: 'auth.logout',
      actorType: $event->userId ? 'user' : 'anonymous',
      actorId: $event->userId,
      ipAddress: $this->sanitizer->ip($ip),
      ipHash: $this->sanitizer->ipHash($ip),
      userAgent: $context['user_agent'],
      metadata: $this->withRequestMeta([
        'refresh_token_revoked' => $event->refreshTokenRevoked,
        'access_token_revoked' => $event->accessTokenRevoked,
      ]),
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
    $context = $this->requestContext();
    $ip = $event->ipAddress ?? $context['ip'];
    $actorType = $event->userId ? 'user' : 'client';
    $actorId = $event->userId ?? $event->clientId;

    $this->dispatchAuditEvent(new RecordAuditEventCommand(
      action: 'auth.token_issued',
      actorType: $actorType,
      actorId: $actorId,
      subjectType: 'token',
      subjectId: $event->tokenId,
      clientId: $event->clientId,
      ipAddress: $this->sanitizer->ip($ip),
      ipHash: $this->sanitizer->ipHash($ip),
      userAgent: $context['user_agent'],
      metadata: $this->withRequestMeta([
        'grant_type' => $event->grantType,
        'scopes' => $event->scopes,
        'expires_in' => $event->expiresIn,
        'user_id' => $event->userId,
      ]),
      occurredAt: $event->occurredAt,
    ));
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
    $context = $this->requestContext();
    $ip = $event->ipAddress ?? $context['ip'];

    $this->dispatchAuditEvent(new RecordAuditEventCommand(
      action: 'oauth.token_issued',
      actorType: 'client',
      actorId: $event->clientId,
      subjectType: 'token',
      subjectId: $event->tokenId,
      clientId: $event->clientId,
      ipAddress: $this->sanitizer->ip($ip),
      ipHash: $this->sanitizer->ipHash($ip),
      userAgent: $context['user_agent'],
      metadata: $this->withRequestMeta([
        'grant_type' => $event->grantType,
        'scopes' => $event->scopes,
        'expires_in' => $event->expiresIn,
        'user_id' => $event->userId,
      ]),
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
    $context = $this->requestContext();
    $ip = $event->ipAddress ?? $context['ip'];

    $this->dispatchAuditEvent(new RecordAuditEventCommand(
      action: 'oauth.token_issue_failed',
      actorType: 'client',
      actorId: $event->clientId,
      clientId: $event->clientId,
      ipAddress: $this->sanitizer->ip($ip),
      ipHash: $this->sanitizer->ipHash($ip),
      userAgent: $context['user_agent'],
      metadata: $this->withRequestMeta([
        'grant_type' => $event->grantType,
        'reason' => $event->reason,
      ]),
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
    $context = $this->requestContext();
    $ip = $event->ipAddress ?? $context['ip'];

    $this->dispatchAuditEvent(new RecordAuditEventCommand(
      action: 'oauth.token_refreshed',
      actorType: 'user',
      actorId: $event->userId,
      ipAddress: $this->sanitizer->ip($ip),
      ipHash: $this->sanitizer->ipHash($ip),
      userAgent: $context['user_agent'],
      metadata: $this->withRequestMeta([]),
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
    $context = $this->requestContext();
    $ip = $event->ipAddress ?? $context['ip'];

    $this->dispatchAuditEvent(new RecordAuditEventCommand(
      action: 'oauth.token_refresh_failed',
      actorType: 'user',
      actorId: $event->userId,
      ipAddress: $this->sanitizer->ip($ip),
      ipHash: $this->sanitizer->ipHash($ip),
      userAgent: $context['user_agent'],
      metadata: $this->withRequestMeta([
        'reason' => $event->reason,
      ]),
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
    $context = $this->requestContext();
    $ip = $event->ipAddress ?? $context['ip'];

    $this->dispatchAuditEvent(new RecordAuditEventCommand(
      action: 'oauth.token_revoked',
      actorType: $event->clientId ? 'client' : 'system',
      actorId: $event->clientId,
      subjectType: 'token',
      subjectId: $event->tokenId,
      clientId: $event->clientId,
      ipAddress: $this->sanitizer->ip($ip),
      ipHash: $this->sanitizer->ipHash($ip),
      userAgent: $context['user_agent'],
      metadata: $this->withRequestMeta([
        'token_type' => $event->tokenType,
        'reason' => $event->reason,
        'user_id' => $event->userId,
      ]),
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
    $context = $this->requestContext();
    $ip = $context['ip'];
    $action = $event->isNew ? 'oauth.consent_granted' : 'oauth.consent_updated';

    $this->dispatchAuditEvent(new RecordAuditEventCommand(
      action: $action,
      actorType: 'user',
      actorId: $event->userId,
      subjectType: 'client',
      subjectId: $event->clientId,
      clientId: $event->clientId,
      ipAddress: $this->sanitizer->ip($ip),
      ipHash: $this->sanitizer->ipHash($ip),
      userAgent: $context['user_agent'],
      metadata: $this->withRequestMeta([
        'scopes' => $event->scopes,
        'is_new' => $event->isNew,
      ]),
      occurredAt: $event->occurredAt,
    ));
  }

  /**
   * Method dispatchAuditEvent.
   *
   * Dispatches a RecordAuditEventCommand and swallows errors.
   *
   * @since 1.0.0
   *
   * @param RecordAuditEventCommand $command the command to dispatch
   */
  private function dispatchAuditEvent(RecordAuditEventCommand $command): void
  {
    try {
      $this->commandBus->dispatch(command: $command);
    } catch (Throwable $exception) {
      $this->logger->error('Failed to record audit event', [
        'error' => $exception->getMessage(),
        'action' => $command->action,
      ]);
    }
  }

  /**
   * Method requestContext.
   *
   * Extracts request context (ip, user agent, request id).
   *
   * @since 1.0.0
   *
   * @return array{ip: ?string, user_agent: ?string, request_id: ?string}
   */
  private function requestContext(): array
  {
    $request = $this->requestStack->getCurrentRequest();

    return [
      'ip' => $request?->getClientIp(),
      'user_agent' => $request?->headers->get('User-Agent'),
      'request_id' => $request?->headers->get('X-Request-Id')
        ?? $request?->headers->get('X-Correlation-Id'),
    ];
  }

  /**
   * Method withRequestMeta.
   *
   * Appends request metadata to the event payload.
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $metadata the base metadata
   *
   * @return array<string, mixed> the enriched metadata
   */
  private function withRequestMeta(array $metadata): array
  {
    $context = $this->requestContext();
    if (null !== $context['request_id']) {
      $metadata['request_id'] = $context['request_id'];
    }

    return $metadata;
  }
}
