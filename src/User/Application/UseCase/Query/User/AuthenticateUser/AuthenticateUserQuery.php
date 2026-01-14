<?php

declare(strict_types=1);

namespace User\Application\UseCase\Query\User\AuthenticateUser;

/**
 * Query AuthenticateUserQuery.
 *
 * @final
 *
 * Query to authenticate a user.
 *
 * @category Query
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
use Shared\Application\Message\QueryMessage;

/**
 * Query AuthenticateUserQuery.
 *
 * @category Query
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AuthenticateUserQuery implements QueryMessage
{
  // #region Methods
  /**
   * Constructor.
   *
   * Initializes a new instance of the AuthenticateUserQuery class.
   *
   * @since 1.0.0
   *
   * @param string $username the username
   * @param string $password the plain text password
   */
  public function __construct(
    public readonly string $username,
    public readonly string $password,
  ) {
  }
  // #enregion
}
