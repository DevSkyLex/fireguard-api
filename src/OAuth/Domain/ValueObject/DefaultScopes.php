<?php

declare(strict_types=1);

namespace OAuth\Domain\ValueObject;

/**
 * Class DefaultScopes
 * @final
 *
 * Defines default OAuth2 scopes for different contexts.
 *
 * @category ValueObject
 * @package Auth\Domain\ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DefaultScopes
{
  //#region Constants
  /**
   * Constant USER_SCOPES
   *
   * Default scopes for authenticated users.
   *
   * @access public
   * @since 1.0.0
   *
   * @var list<string>
   */
  public const array USER_SCOPES = [
    'OPENID',
    'PROFILE',
    'EMAIL',
    'READ',
    'WRITE',
  ];

  /**
   * Constant CLIENT_SCOPES
   *
   * Default scopes for client credentials grant.
   *
   * @access public
   * @since 1.0.0
   *
   * @var list<string>
   */
  public const array CLIENT_SCOPES = [
    'READ',
    'WRITE',
  ];

  /**
   * Constant OPENID_SCOPES
   *
   * OpenID Connect standard scopes.
   *
   * @access public
   * @since 1.0.0
   *
   * @var list<string>
   */
  public const array OPENID_SCOPES = [
    'OPENID',
    'PROFILE',
    'EMAIL',
  ];
  //#endregion
}
