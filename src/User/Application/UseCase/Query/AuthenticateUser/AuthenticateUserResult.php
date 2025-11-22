<?php

declare(strict_types=1);

namespace User\Application\UseCase\Query\AuthenticateUser;

/**
 * Result AuthenticateUserResult
 * @final
 *
 * Result of user authentication.
 *
 * @category Result
 * @package User\Application\UseCase\Query\AuthenticateUser
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
use Shared\Application\Message\ResultMessage;

/**
 * Result AuthenticateUserResult
 * @final
 *
 * Result of user authentication.
 *
 * @category Result
 * @package User\Application\UseCase\Query\AuthenticateUser
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AuthenticateUserResult implements ResultMessage
{
  //#region Constructor
  /**
   * Constructor
   * 
   * Initializes a new instance of the AuthenticateUserResult class.
   * 
   * @access public
   * @since 1.0.0
   *
   * @param bool $authenticated Whether authentication was successful.
   * @param string|null $userId The user ID if authenticated.
   * @param string|null $email The user email if authenticated.
   * @param string|null $fullName The user's full name if authenticated.
   */
  public function __construct(
    public readonly bool $authenticated,
    public readonly ?string $userId = null,
    public readonly ?string $email = null,
    public readonly ?string $fullName = null,
  ) {
  }
  //#endregion
}
