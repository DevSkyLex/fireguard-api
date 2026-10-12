<?php

declare(strict_types=1);

namespace Audit\Application\Contract;

use DateTimeImmutable;

/**
 * Class AuditRecordRequest
 *
 * Describes an owner-authorized audit fact. Audit resolves omitted actor/context fields and sanitizes personal data before durable enqueue.
 *
 * @category Contract
 */
final readonly class AuditRecordRequest
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Captures scalar audit facts without a persistence or domain dependency. Email metadata becomes a governed value plus a keyed hash.
   *
   * @access public
   *
   * @param string $action stable owner-defined action
   * @param ?string $actorType explicit principal kind; null resolves the current actor
   * @param ?string $actorId explicit principal identifier
   * @param ?string $actorEmail raw email, governed by Audit
   * @param ?string $subjectType owner-defined subject kind
   * @param ?string $subjectId opaque subject identifier
   * @param ?string $clientId OAuth client identifier
   * @param ?string $tenantId tenant scope
   * @param ?string $organizationId organization scope, synchronized into hash-covered metadata
   * @param ?string $ipAddress raw address, governed by Audit
   * @param ?string $userAgent optional request user agent
   * @param array<string, mixed> $metadata owner-defined non-credential facts
   * @param ?DateTimeImmutable $occurredAt original occurrence time; null means now
   * @param array<string, ?string> $emailMetadata email facts requiring Audit PII handling
   *
   * @return void
   */
  public function __construct(
    public string $action,
    public ?string $actorType = null,
    public ?string $actorId = null,
    public ?string $actorEmail = null,
    public ?string $subjectType = null,
    public ?string $subjectId = null,
    public ?string $clientId = null,
    public ?string $tenantId = null,
    public ?string $organizationId = null,
    public ?string $ipAddress = null,
    public ?string $userAgent = null,
    public array $metadata = [],
    public ?DateTimeImmutable $occurredAt = null,
    public array $emailMetadata = [],
  ) {
  }
  // #endregion
}
