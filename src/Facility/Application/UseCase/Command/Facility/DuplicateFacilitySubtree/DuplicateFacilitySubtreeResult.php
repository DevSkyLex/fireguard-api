<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Command\Facility\DuplicateFacilitySubtree;

use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase DuplicateFacilitySubtreeResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DuplicateFacilitySubtreeResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $facilityId the new root facility identifier
   * @param string $organizationId the organization identifier
   * @param ?string $parentFacilityId the new root's parent facility identifier
   * @param string $type the facility type
   * @param string $name the new root's name
   * @param ?string $code always null on a clone
   * @param string $status the facility status, always active on a clone
   * @param ?string $address the optional address
   * @param array<string, mixed> $metadata the optional metadata
   * @param DateTimeImmutable $createdAt the creation timestamp
   * @param DateTimeImmutable $updatedAt the update timestamp
   * @param ?float $latitude the optional latitude
   * @param ?float $longitude the optional longitude
   * @param int $nodeCount the number of facility nodes created (new root included)
   */
  public function __construct(
    public string $facilityId,
    public string $organizationId,
    public ?string $parentFacilityId,
    public string $type,
    public string $name,
    public ?string $code,
    public string $status,
    public ?string $address,
    public array $metadata,
    public DateTimeImmutable $createdAt,
    public DateTimeImmutable $updatedAt,
    public ?float $latitude,
    public ?float $longitude,
    public int $nodeCount,
  ) {
  }
  // #endregion
}
