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

  /**
   * Group EMAIL_CHANGE_READ.
   *
   * Used for reading email change flow responses.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string EMAIL_CHANGE_READ = 'email_change:read';

  /**
   * Group EMAIL_CHANGE_WRITE.
   *
   * Used for writing email change flow inputs.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string EMAIL_CHANGE_WRITE = 'email_change:write';
}
