<?php

declare(strict_types=1);

namespace Authorization\Presentation\Api\Serialization;

/**
 * Class PermissionSerializationGroup
 * @final
 *
 * Serialization groups for Permission API.
 *
 * @category Serialization
 * @package Authorization\Presentation\Api\Serialization
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class PermissionSerializationGroup
{
  //#region Constants
  /**
   * Constant READ
   * 
   * Group for reading permission data.
   * 
   * @access public
   * @since 1.0.0
   * 
   * @var string
   */
  public const string READ = 'permission:read';

  /**
   * Constant WRITE
   * 
   * Group for writing permission data.
   * 
   * @access public
   * @since 1.0.0
   * 
   * @var string
   */
  public const string WRITE = 'permission:write';

  /**
   * Constant UPDATE
   * 
   * Group for updating permission data.
   * 
   * @access public
   * @since 1.0.0
   * 
   * @var string
   */
  public const string UPDATE = 'permission:update';
  //#endregion
}
