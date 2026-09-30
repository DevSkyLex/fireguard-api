<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Query\Tag\ListTags;

use Shared\Application\Contract\Pagination\Pagination;
use Shared\Application\Message\QueryMessage;

/**
 * UseCase ListTagsQuery.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListTagsQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Carries an organization scope, optional search text, and pagination for tag listing.
   *
   * @access public
   *
   * @param string $organizationId organization whose tags are listed
   * @param ?string $search optional text used to filter tag names
   * @param Pagination $pagination requested page and page size
   *
   * @return void
   */
  public function __construct(
    public string $organizationId,
    public ?string $search = null,
    public Pagination $pagination = new Pagination(),
  ) {
  }
  // #endregion
}
