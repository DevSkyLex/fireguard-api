<?php

declare(strict_types=1);

namespace Audit\Presentation\Api\Serialization;

/**
 * Serialization groups for audit API.
 *
 * @category Serialization
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class AuditSerializationGroup
{
  /**
   * Group for read-only audit views.
   *
   * @since 1.0.0
   */
  public const string READ = 'audit:read';
}
