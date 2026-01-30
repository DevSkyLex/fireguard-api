<?php

declare(strict_types=1);

namespace Audit\Presentation\Api\Operation;

/**
 * Operation names for audit API.
 *
 * @category Operation
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class AuditOperations
{
  /**
   * List audit events operation.
   *
   * @since 1.0.0
   */
  public const string LIST = 'audit_events_list';

  /**
   * Get audit event operation.
   *
   * @since 1.0.0
   */
  public const string GET = 'audit_event_get';
}
