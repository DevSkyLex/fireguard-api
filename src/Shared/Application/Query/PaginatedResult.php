<?php

declare(strict_types=1);

namespace Shared\Application\Query;

use Shared\Application\Message\ResultMessage;

/**
 * Result PaginatedResult
 * @final
 *
 * Represents a paginated result set.
 *
 * @category Result
 * @package Shared\Application\Query
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 * 
 * @template T
 */
final readonly class PaginatedResult implements ResultMessage
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the PaginatedResult class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param array<T> $items The items in the current page.
   * @param int $total The total number of items.
   * @param int $limit The limit per page.
   * @param int $offset The current offset.
   */
  public function __construct(
    public readonly array $items,
    public readonly int $total,
    public readonly int $limit,
    public readonly int $offset
  ) {}
  //#endregion
}
