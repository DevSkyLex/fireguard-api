<?php

declare(strict_types=1);

namespace Client\Presentation\Api\Serialization;

/**
 * Class ClientSerializationGroup
 * @final
 *
 * Serialization groups for Client module.
 *
 * @category Serialization
 * @package Client\Presentation\Api\Serialization
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ClientSerializationGroup
{
  /**
   * Group READ
   * 
   * Used for reading client data.
   * 
   * @access public
   * @since 1.0.0
   * 
   * @var string
   */
  public const string READ = 'client:read';

  /**
   * Group WRITE
   * 
   * Used for writing/creating client data.
   * 
   * @access public
   * @since 1.0.0
   * 
   * @var string
   */
  public const string WRITE = 'client:write';

  /**
   * Group UPDATE
   * 
   * Used for updating client data.
   * 
   * @access public
   * @since 1.0.0
   * 
   * @var string
   */
  public const string UPDATE = 'client:update';

  /**
   * Group SECRET
   * 
   * Used for exposing client secret.
   * 
   * @access public
   * @since 1.0.0
   * 
   * @var string
   */
  public const string SECRET = 'client:secret';
}
