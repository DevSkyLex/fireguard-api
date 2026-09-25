<?php

declare(strict_types=1);

namespace Audit\Infrastructure\EventSubscriber;

use Audit\Application\UseCase\Command\RecordAuditEvent\RecordAuditEventCommand;
use Auth\Domain\Event\Session\{LoginFailedEvent, UserLoggedInEvent, UserLoggedOutEvent};
use Auth\Domain\Event\Token\TokenIssuedEvent as AuthTokenIssuedEvent;
use DateTimeImmutable;
use OAuth\Domain\Event\Consent\ConsentGrantedEvent;
use OAuth\Domain\Event\Token\{TokenIssueFailedEvent, TokenIssuedEvent, TokenRefreshFailedEvent, TokenRefreshedEvent, TokenRevokedEvent};
use Otp\Domain\Event\Totp\{TotpEnrollmentConfirmedEvent, TotpEnrollmentDisabledEvent};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use User\Domain\Event\{UserEmailChangeCancelledEvent, UserEmailChangeConfirmedEvent, UserEmailChangeRequestedEvent};

/** Records the security event family. */
final readonly class SecurityAuditEventSubscriber extends AbstractAuditEventSubscriber implements EventSubscriberInterface
{
  /**
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
      'otp.totp_enrollment_confirmed_event' => 'onTotpEnrollmentConfirmed',
      'otp.totp_enrollment_disabled_event' => 'onTotpEnrollmentDisabled',
      'user.user_email_change_requested_event' => 'onUserEmailChangeRequested',
      'user.user_email_change_confirmed_event' => 'onUserEmailChangeConfirmed',
      'user.user_email_change_cancelled_event' => 'onUserEmailChangeCancelled',
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
   * Method onTotpEnrollmentConfirmed.
   *
   * Records a TOTP enrollment activation.
   *
   * @since 1.0.0
   *
   * @param TotpEnrollmentConfirmedEvent $event the domain event
   */
  public function onTotpEnrollmentConfirmed(TotpEnrollmentConfirmedEvent $event): void
  {
    $context = $this->requestContext();

    $this->dispatchAuditEvent(new RecordAuditEventCommand(
      action: 'otp.totp_enrolled',
      actorType: 'user',
      actorId: $event->userId,
      ipAddress: $this->sanitizer->ip($context['ip']),
      ipHash: $this->sanitizer->ipHash($context['ip']),
      userAgent: $context['user_agent'],
      metadata: $this->withRequestMeta([]),
      occurredAt: $event->occurredAt,
    ));
  }

  /**
   * Method onTotpEnrollmentDisabled.
   *
   * Records a TOTP enrollment being disabled.
   *
   * @since 1.0.0
   *
   * @param TotpEnrollmentDisabledEvent $event the domain event
   */
  public function onTotpEnrollmentDisabled(TotpEnrollmentDisabledEvent $event): void
  {
    $context = $this->requestContext();

    $this->dispatchAuditEvent(new RecordAuditEventCommand(
      action: 'otp.totp_disabled',
      actorType: 'user',
      actorId: $event->userId,
      ipAddress: $this->sanitizer->ip($context['ip']),
      ipHash: $this->sanitizer->ipHash($context['ip']),
      userAgent: $context['user_agent'],
      metadata: $this->withRequestMeta([]),
      occurredAt: $event->occurredAt,
    ));
  }

  /**
   * Method onUserEmailChangeRequested.
   *
   * Records a sign-in email change request. Both addresses are PII:
   * they go through the sanitizer like every other email in the
   * ledger — sanitized display form plus deterministic hash, never
   * the raw value in the metadata.
   *
   * @since 1.0.0
   *
   * @param UserEmailChangeRequestedEvent $event the domain event
   */
  public function onUserEmailChangeRequested(UserEmailChangeRequestedEvent $event): void
  {
    $this->recordEmailChangeAudit('user.email_change_requested', $event->userId, $event->currentEmail, $event->newEmail, $event->occurredAt);
  }

  /**
   * Method onUserEmailChangeConfirmed.
   *
   * Records an applied sign-in email change (sessions revoked).
   *
   * @since 1.0.0
   *
   * @param UserEmailChangeConfirmedEvent $event the domain event
   */
  public function onUserEmailChangeConfirmed(UserEmailChangeConfirmedEvent $event): void
  {
    $this->recordEmailChangeAudit('user.email_change_confirmed', $event->userId, $event->currentEmail, $event->newEmail, $event->occurredAt);
  }

  /**
   * Method onUserEmailChangeCancelled.
   *
   * Records a cancelled sign-in email change request.
   *
   * @since 1.0.0
   *
   * @param UserEmailChangeCancelledEvent $event the domain event
   */
  public function onUserEmailChangeCancelled(UserEmailChangeCancelledEvent $event): void
  {
    $this->recordEmailChangeAudit('user.email_change_cancelled', $event->userId, $event->currentEmail, $event->newEmail, $event->occurredAt);
  }

  /**
   * Method recordEmailChangeAudit.
   *
   * Shared shape of the three email-change ledger entries.
   *
   * @since 1.0.0
   *
   * @param string $action the audit action
   * @param string $userId the acting user
   * @param string $currentEmail the current (old) address
   * @param string $newEmail the requested/applied new address
   * @param DateTimeImmutable $occurredAt when the event occurred
   */
  private function recordEmailChangeAudit(
    string $action,
    string $userId,
    string $currentEmail,
    string $newEmail,
    DateTimeImmutable $occurredAt,
  ): void {
    $context = $this->requestContext();

    $this->dispatchAuditEvent(new RecordAuditEventCommand(
      action: $action,
      actorType: 'user',
      actorId: $userId,
      actorEmail: $this->sanitizer->email($currentEmail),
      actorEmailHash: $this->sanitizer->emailHash($currentEmail),
      subjectType: 'user',
      subjectId: $userId,
      ipAddress: $this->sanitizer->ip($context['ip']),
      ipHash: $this->sanitizer->ipHash($context['ip']),
      userAgent: $context['user_agent'],
      metadata: $this->withRequestMeta([
        'current_email' => $this->sanitizer->email($currentEmail),
        'current_email_hash' => $this->sanitizer->emailHash($currentEmail),
        'new_email' => $this->sanitizer->email($newEmail),
        'new_email_hash' => $this->sanitizer->emailHash($newEmail),
      ]),
      occurredAt: $occurredAt,
    ));
  }
}
