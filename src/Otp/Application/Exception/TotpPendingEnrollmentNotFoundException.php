<?php

declare(strict_types=1);

namespace Otp\Application\Exception;

use Shared\Application\Exception\ApplicationException;

use function sprintf;

/**
 * Exception TotpPendingEnrollmentNotFoundException.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class TotpPendingEnrollmentNotFoundException extends ApplicationException
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
      message: sprintf('No pending TOTP setup found for user "%s". Call setup first.', $userId),
    );
  }
  // #endregion

  // #region Methods
  public static function forUser(string $userId): self
  {
    return new self(userId: $userId);
  }

  public function context(): array
  {
    return [
      'userId' => $this->userId,
    ];
  }
  // #endregion
}
