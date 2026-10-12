<?php

declare(strict_types=1);

namespace Audit\Infrastructure\Service;

use Audit\Application\Contract\{AuditRecordPayload, AuditRecordRequest};
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class AuditRecordPayloadFactory
 *
 * Centralizes actor attribution, request context and PII reduction before a fact enters either outbox.
 *
 * @category Service
 */
final readonly class AuditRecordPayloadFactory
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Receives Audit-owned privacy and request-context collaborators.
   *
   * @access public
   *
   * @param AuditPiiSanitizer $sanitizer governed PII policy
   * @param AuditActorResolver $actors explicit, delivery or current principal resolver
   * @param RequestStack $requests ambient HTTP context
   *
   * @return void
   */
  public function __construct(private AuditPiiSanitizer $sanitizer, private AuditActorResolver $actors, private RequestStack $requests)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method create
   *
   * Reduces raw personal data once, so delayed delivery cannot change policy or actor attribution.
   *
   * @access public
   *
   * @param AuditRecordRequest $request owner-authorized audit fact
   *
   * @return AuditRecordPayload durable, sanitized snapshot
   */
  public function create(AuditRecordRequest $request): AuditRecordPayload
  {
    $actor = null === $request->actorType ? $this->actors->resolve($request->actorId, $request->actorEmail) : [
      'type' => $request->actorType,
      'id' => $request->actorId,
      'email' => $request->actorEmail,
    ];
    $http = $this->requests->getCurrentRequest();
    $ip = $request->ipAddress ?? $http?->getClientIp();
    $metadata = [];
    foreach ($request->emailMetadata as $key => $email) {
      $metadata[$key] = $this->sanitizer->email($email);
      $metadata[$key . '_hash'] = $this->sanitizer->emailHash($email);
    }
    $metadata += $request->metadata;
    if (null !== $request->organizationId) {
      $metadata['organization_id'] = $request->organizationId;
    }
    $requestId = $http?->headers->get('X-Request-Id') ?? $http?->headers->get('X-Correlation-Id');
    if (null !== $requestId) {
      $metadata['request_id'] = $requestId;
    }

    return new AuditRecordPayload(
      action: $request->action,
      actorType: $actor['type'],
      actorId: $actor['id'],
      actorEmail: $this->sanitizer->email($actor['email']),
      actorEmailHash: $this->sanitizer->emailHash($actor['email']),
      subjectType: $request->subjectType,
      subjectId: $request->subjectId,
      clientId: $request->clientId,
      tenantId: $request->tenantId,
      organizationId: $request->organizationId,
      ipAddress: $this->sanitizer->ip($ip),
      ipHash: $this->sanitizer->ipHash($ip),
      userAgent: $request->userAgent ?? $http?->headers->get('User-Agent'),
      metadata: $metadata,
      occurredAt: $request->occurredAt ?? new DateTimeImmutable(),
    );
  }
  // #endregion
}
