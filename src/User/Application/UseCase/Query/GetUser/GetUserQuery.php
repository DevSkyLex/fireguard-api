<?php

declare(strict_types=1);

namespace User\Application\UseCase\Query\GetUser;

use Shared\Application\Message\QueryMessage;

/**
 * Query GetUserQuery
 * @final
 *
 * Query to get a user by ID.
 *
 * @category Query
 * @package User\Application\UseCase\Query\GetUser
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetUserQuery implements QueryMessage
{
  //#region Constructor
  /**
   * Constructor
   * 
   * Initializes a new instance of the GetUserQuery class.
   * 
   * @access public
   * @since 1.0.0
   *
   * @param string $id The user ID.
   */
  public function __construct(
    public readonly string $id
  ) {}
  //#endregion
}
