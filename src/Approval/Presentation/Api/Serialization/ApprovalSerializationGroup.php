<?php

declare(strict_types=1);

namespace Approval\Presentation\Api\Serialization;

/**
 * Serialization ApprovalSerializationGroup.
 *
 * @category Serialization
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ApprovalSerializationGroup
{
  public const string READ = 'Approval:read';

  public const string WRITE = 'Approval:write';
}
