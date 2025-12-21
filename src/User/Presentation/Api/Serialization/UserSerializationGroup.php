<?php

declare(strict_types=1);

namespace User\Presentation\Api\Serialization;

/**
 * Class UserSerializationGroup.
 *
 * @category Serialization
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UserSerializationGroup
{
  /**
   * Group READ.
   *
   * Used for reading user data.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string READ = 'user:read';

  /**
   * Group WRITE.
   *
   * Used for writing/creating user data.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string WRITE = 'user:write';
}
