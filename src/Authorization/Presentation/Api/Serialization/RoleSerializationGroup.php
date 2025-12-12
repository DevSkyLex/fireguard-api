<?php

declare(strict_types=1);

namespace Authorization\Presentation\Api\Serialization;

/**
 * Class RoleSerializationGroup
 * @final
 *
 * Serialization groups for Role API.
 *
 * @category Serialization
 * @package Authorization\Presentation\Api\Serialization
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class RoleSerializationGroup
{
  //#region Constants
  /**
   * Constant READ
   * 
   * Group for reading role data.
   * 
   * @access public
   * @since 1.0.0
   * 
   * @var string
   */
  public const string READ = 'role:read';

  /**
   * Constant WRITE
   * 
   * Group for writing role data.
   * 
   * @access public
   * @since 1.0.0
   * 
   * @var string
   */
  public const string WRITE = 'role:write';

  /**
   * Constant UPDATE
   * 
   * Group for updating role data.
   * 
   * @access public
   * @since 1.0.0
   * 
   * @var string
   */
  public const string UPDATE = 'role:update';
  //#endregion
}
