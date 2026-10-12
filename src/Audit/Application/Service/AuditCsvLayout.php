<?php

declare(strict_types=1);

namespace Audit\Application\Service;

use Audit\Application\Contract\AuditEventView;

use function json_encode;

use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * Defines the existing public CSV representation independently of its output stream.
 *
 * @category Service
 */
final readonly class AuditCsvLayout
{
  /**
   * @return list<string> public CSV columns in their original order
   */
  public static function header(): array
  {
    return ['id', 'action', 'actor_type', 'actor_id', 'actor_email', 'actor_email_hash', 'subject_type', 'subject_id', 'client_id', 'tenant_id', 'ip_address', 'ip_hash', 'user_agent', 'metadata', 'occurred_at', 'recorded_at', 'chain_id', 'sequence', 'prev_hash', 'event_hash'];
  }

  /**
   * @param AuditEventView $view immutable ledger projection
   *
   * @return list<string> original scalar CSV cells
   */
  public static function row(AuditEventView $view): array
  {
    return [
      $view->id, $view->action, $view->actorType, $view->actorId ?? '', $view->actorEmail ?? '', $view->actorEmailHash ?? '',
      $view->subjectType ?? '', $view->subjectId ?? '', $view->clientId ?? '', $view->tenantId ?? '', $view->ipAddress ?? '',
      $view->ipHash ?? '', $view->userAgent ?? '', (string) json_encode($view->metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
      $view->occurredAt, $view->recordedAt, $view->chainId, (string) $view->sequence, $view->prevHash ?? '', $view->eventHash,
    ];
  }
}
