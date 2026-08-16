<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Query\MetadataField\ListMetadataFields;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase ListMetadataFieldsQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListMetadataFieldsQuery implements QueryMessage
{
  // #region Constructor
  public function __construct(
    public string $organizationId,
  ) {
  }
  // #endregion
}
