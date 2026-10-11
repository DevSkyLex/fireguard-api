<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Query\Attachment\GetFacilityMediaContext;

use Shared\Application\Message\QueryMessage;

/**
 * Query GetFacilityMediaContextQuery.
 *
 * Resolves the owning facility and checks access before transport validation.
 *
 * @category Query
 */
final readonly class GetFacilityMediaContextQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Method __construct.
   */
  public function __construct(
    public string $userId,
    public ?string $facilityId = null,
    public ?string $attachmentId = null,
    public bool $write = false,
  ) {
  }
  // #endregion
}
