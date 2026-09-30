<?php

declare(strict_types=1);

namespace Otp\Application\Exception;

use Shared\Application\Exception\ApplicationException;

use function sprintf;

/**
 * Exception TotpEnrollmentNotEnabledException.
 *
 * @category Exception
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class TotpEnrollmentNotEnabledException extends ApplicationException
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @param string $userId the user ID
   */
  public function __construct(private readonly string $userId)
  {
    parent::__construct(
      message: sprintf('TOTP is not enabled for user "%s".', $userId),
    );
  }

  // #endregion
  // #region Methods
  /**
   * Method forUser
   *
   * Creates the exception indicating that TOTP enrollment is unavailable to the user.
   *
   * @access public
   *
   * @param string $userId the user identifier
   *
   * @return self the created instance
   */
  public static function forUser(string $userId): self
  {
    return new self(userId: $userId);
  }

  /**
   * Method context
   *
   * Returns the structured context associated with this exception.
   *
   * @access public
   *
   * @return array{userId: string} exception context map
   */
  public function context(): array
  {
    return [
      'userId' => $this->userId,
    ];
  }
  // #endregion
}
