<?php

declare(strict_types=1);

namespace Approval\Presentation\Api\Serialization;

/**
 * Serialization ApprovalSerializationGroup.
 *
 * @category Serialization
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ApprovalSerializationGroup
{
  /**
   * Constant READ
   */
  public const string READ = 'Approval:read';

  /**
   * Constant WRITE
   */
  public const string WRITE = 'Approval:write';
}
