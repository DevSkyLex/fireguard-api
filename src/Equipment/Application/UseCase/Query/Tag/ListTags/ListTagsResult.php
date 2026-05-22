<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Query\Tag\ListTags;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase ListTagsResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListTagsResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param list<array{id: string, name: string, organizationId: string}> $tags the tag list
   * @param int $total the total count unaffected by pagination
   */
  public function __construct(
    public array $tags,
    public int $total,
  ) {
  }
  // #endregion
}
