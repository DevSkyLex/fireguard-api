<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Query\Media\GetEquipmentMedia;

use Shared\Application\Message\QueryMessage;

/**
 * Requests one media item for an authenticated reader.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetEquipmentMediaQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Constructor. @since 1.0.0
   */
  public function __construct(public string $id, public ?string $userId, public bool $write = false)
  {
  }
  // #endregion
}
