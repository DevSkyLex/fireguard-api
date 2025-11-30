<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Serialization;

/**
 * Class AuthSerializationGroup
 * @final
 *
 * Serialization groups for Auth module.
 *
 * @category Serialization
 * @package Auth\Presentation\Api\Serialization
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AuthSerializationGroup
{
  /**
   * Group READ
   * 
   * Used for reading token data.
   * 
   * @access public
   * @since 1.0.0
   * 
   * @var string
   */
  public const string READ = 'token:read';

  /**
   * Group WRITE
   * 
   * Used for writing token data.
   * 
   * @access public
   * @since 1.0.0
   * 
   * @var string
   */
  public const string WRITE = 'token:write';
}
