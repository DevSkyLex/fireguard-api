<?php

declare(strict_types=1);

namespace User\Application\UseCase\Query\AuthenticateUser;

/**
 * Query AuthenticateUserQuery
 * @final
 *
 * Query to authenticate a user.
 *
 * @category Query
 * @package User\Application\UseCase\Query\AuthenticateUser
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
use Shared\Application\Message\QueryMessage;

/**
 * Query AuthenticateUserQuery
 * @final
 *
 * Query to authenticate a user.
 *
 * @category Query
 * @package User\Application\UseCase\Query\AuthenticateUser
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AuthenticateUserQuery implements QueryMessage
{
  //#region Methods
  /**
   * Constructor
   * 
   * Initializes a new instance of the AuthenticateUserQuery class.
   * 
   * @access public
   * @since 1.0.0
   *
   * @param string $username The username.
   * @param string $password The plain text password.
   */
  public function __construct(
    public readonly string $username,
    public readonly string $password,
  ) {}
  //#enregion
}
