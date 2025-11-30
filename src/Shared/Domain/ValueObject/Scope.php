<?php

declare(strict_types=1);

namespace Shared\Domain\ValueObject;

/**
 * Enum Scope
 *
 * Represents an OAuth 2.0 scope.
 * Scopes define the level of access granted to a client.
 *
 * @category ValueObject
 * @package Shared\Domain\ValueObject
 * @version 2.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
enum Scope: string
{
  //#region Cases
  /**
   * Case OPENID
   * 
   * OpenID scope.
   */
  case OPENID = 'OPENID';

  /**
   * Case PROFILE
   * 
   * Profile scope.
   */
  case PROFILE = 'PROFILE';

  /**
   * Case EMAIL
   * 
   * Email scope.
   */
  case EMAIL = 'EMAIL';

  /**
   * Case PHONE
   * 
   * Phone scope.
   */
  case PHONE = 'PHONE';

  /**
   * Case ADDRESS
   * 
   * Address scope.
   */
  case ADDRESS = 'ADDRESS';

  /**
   * Case READ
   * 
   * Read scope.
   */
  case READ = 'READ';

  /**
   * Case WRITE
   * 
   * Write scope.
   */
  case WRITE = 'WRITE';

  /**
   * Case ADMIN
   * 
   * Admin scope.
   */
  case ADMIN = 'ADMIN';

  /**
   * Case DELETE
   * 
   * Delete scope.
   */
  case DELETE = 'DELETE';
  //#endregion

  /**
   * Method values
   *
   * Returns an array of all possible values.
   *
   * @access public
   * @since 2.0.0
   *
   * @return list<string> An array of values.
   */
  public static function values(): array
  {
    return array_column(self::cases(), 'value');
  }
}
