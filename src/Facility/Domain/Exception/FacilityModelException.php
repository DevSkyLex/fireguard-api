<?php

declare(strict_types=1);

namespace Facility\Domain\Exception;

use RuntimeException;

/**
 * Exception FacilityModelException.
 *
 * @category Exception
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class FacilityModelException extends RuntimeException
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Initializes the model capability with its typed dependencies and state.
   *
   * @access private
   * @since 1.0.0
   *
   * @param string $reason the reason
   * @param string $message the message
   *
   * @return void no return value
   */
  private function __construct(public readonly string $reason, string $message)
  {
    parent::__construct($message);
  }
  // #endregion

  // #region Methods
  /**
   * Method notFound.
   *
   * Creates the named domain failure without coupling it to HTTP status codes.
   *
   * @access public
   * @since 1.0.0
   *
   * @return self the operation result
   */
  public static function notFound(): self
  {
    return new self('model_not_found', 'Facility model not found.');
  }

  /**
   * Method denied.
   *
   * Creates the named domain failure without coupling it to HTTP status codes.
   *
   * @access public
   * @since 1.0.0
   *
   * @return self the operation result
   */
  public static function denied(): self
  {
    return new self('model_access_denied', 'Missing organization.facilities permission.');
  }

  /**
   * Method invalid.
   *
   * Creates the named domain failure without coupling it to HTTP status codes.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $message the message
   *
   * @return self the operation result
   */
  public static function invalid(string $message): self
  {
    return new self('model_invalid', $message);
  }

  /**
   * Method stale.
   *
   * Creates the named domain failure without coupling it to HTTP status codes.
   *
   * @access public
   * @since 1.0.0
   *
   * @return self the operation result
   */
  public static function stale(): self
  {
    return new self('model_revision_stale', 'The resource revision is stale.');
  }

  /**
   * Method limit.
   *
   * Creates the named domain failure without coupling it to HTTP status codes.
   *
   * @access public
   * @since 1.0.0
   *
   * @return self the operation result
   */
  public static function limit(): self
  {
    return new self('model_limit_reached', 'A building may contain at most two models. Delete the unused draft before importing another.');
  }
  // #endregion
}
