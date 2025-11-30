<?php

declare(strict_types=1);

namespace User\Presentation\Api\Serialization;

/**
 * Class UserSerializationGroup
 * @final
 *
 * Serialization groups for User module.
 *
 * @category Serialization
 * @package User\Presentation\Api\Serialization
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UserSerializationGroup
{
  /**
   * Group READ
   * 
   * Used for reading user data.
   * 
   * @access public
   * @since 1.0.0
   * 
   * @var string
   */
  public const string READ = 'user:read';

  /**
   * Group WRITE
   * 
   * Used for writing/creating user data.
   * 
   * @access public
   * @since 1.0.0
   * 
   * @var string
   */
  public const string WRITE = 'user:write';
}
