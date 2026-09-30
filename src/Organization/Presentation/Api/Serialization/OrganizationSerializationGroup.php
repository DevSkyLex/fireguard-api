<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Serialization;

/**
 * Serialization OrganizationSerializationGroup.
 *
 * @category Serialization
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationSerializationGroup
{
  /**
   * Constant READ
   */
  public const string READ = 'Organization:read';

  /**
   * Constant WRITE
   */
  public const string WRITE = 'Organization:write';
}
