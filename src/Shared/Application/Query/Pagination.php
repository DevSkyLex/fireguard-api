<?php

declare(strict_types=1);

namespace Shared\Application\Query;

/**
 * ValueObject Pagination
 * @final
 *
 * Represents pagination parameters.
 *
 * @category ValueObject
 * @package Shared\Application\Query
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class Pagination
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the Pagination class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param int $offset The offset (default: 0).
   * @param int $limit The limit (default: 20).
   */
  public function __construct(
    public int $offset = 0,
    public int $limit = 20
  ) {
}
  //#endregion
}
