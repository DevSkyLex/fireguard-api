<?php

declare(strict_types=1);

namespace Otp\Application\Exception;

use Shared\Application\Exception\ApplicationException;

use function sprintf;

/**
 * Exception OtpNotFoundException.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OtpNotFoundException extends ApplicationException
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @param string $identifier the OTP identifier
   */
  public function __construct(private readonly string $identifier)
  {
    parent::__construct(
      message: sprintf('Otp with identifier "%s" not found.', $identifier),
    );
  }
  // #endregion

  // #region Methods
  public static function forIdentifier(string $identifier): self
  {
    return new self(identifier: $identifier);
  }

  public function context(): array
  {
    return [
      'identifier' => $this->identifier,
    ];
  }
  // #endregion
}
