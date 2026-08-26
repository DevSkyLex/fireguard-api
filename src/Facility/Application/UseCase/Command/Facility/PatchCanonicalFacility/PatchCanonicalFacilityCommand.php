<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Command\Facility\PatchCanonicalFacility;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase PatchCanonicalFacilityCommand.
 *
 * The `has*` flags carry the merge-patch distinction the deserialized DTO
 * cannot: an absent key and an explicit `null` both arrive as a null
 * property, and they mean opposite things. `MergePatchFields` reads the raw
 * body in the processor and fills them.
 *
 * `parentFacilityId` is already an identifier: the processor parses the IRI,
 * and the handler resolves and validates it.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class PatchCanonicalFacilityCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @param ?array<string, mixed> $metadata the requested metadata map
   */
  public function __construct(
    public string $facilityId,
    public int $expectedRevision,
    public bool $hasType = false,
    public ?string $type = null,
    public bool $hasName = false,
    public ?string $name = null,
    public bool $hasCode = false,
    public ?string $code = null,
    public bool $hasAddress = false,
    public ?string $address = null,
    public bool $hasLatitude = false,
    public ?float $latitude = null,
    public bool $hasLongitude = false,
    public ?float $longitude = null,
    public bool $hasMetadata = false,
    public ?array $metadata = null,
    public bool $hasStatus = false,
    public ?string $status = null,
    public bool $hasParent = false,
    public ?string $parentFacilityId = null,
  ) {
  }
  // #endregion
}
