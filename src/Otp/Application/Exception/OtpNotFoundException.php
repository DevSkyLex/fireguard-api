<?php

declare(strict_types=1);

namespace Otp\Application\Exception;

use Shared\Application\Exception\ApplicationException;

use function sprintf;

/**
 * Exception OtpNotFoundException.
 *
 * @category Exception
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
  /**
   * Method forIdentifier
   *
   * Creates the exception for a challenge that could not be found by its identifier.
   *
   * @access public
   *
   * @param string $identifier the identifier
   *
   * @return self the created instance
   */
  public static function forIdentifier(string $identifier): self
  {
    return new self(identifier: $identifier);
  }

  /**
   * Method context
   *
   * Returns the structured context associated with this exception.
   *
   * @access public
   *
   * @return array{identifier: string} exception context map
   */
  public function context(): array
  {
    return [
      'identifier' => $this->identifier,
    ];
  }
  // #endregion
}
