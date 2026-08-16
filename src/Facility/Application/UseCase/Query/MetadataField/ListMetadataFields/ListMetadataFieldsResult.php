<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Query\MetadataField\ListMetadataFields;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase ListMetadataFieldsResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListMetadataFieldsResult implements ResultMessage
{
  // #region Constructor
  /**
   * @param list<MetadataFieldItem> $items
   */
  public function __construct(
    public array $items,
  ) {
  }
  // #endregion
}
