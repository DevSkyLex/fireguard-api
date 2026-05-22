<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Query\Tag\ListTags;

use Shared\Application\Contract\Pagination\Pagination;
use Shared\Application\Message\QueryMessage;

/**
 * UseCase ListTagsQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListTagsQuery implements QueryMessage
{
  // #region Constructor
  public function __construct(
    public string $organizationId,
    public ?string $search = null,
    public Pagination $pagination = new Pagination(),
  ) {
  }
  // #endregion
}
