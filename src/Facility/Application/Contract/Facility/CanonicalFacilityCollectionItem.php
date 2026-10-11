<?php

declare(strict_types=1);

namespace Facility\Application\Contract\Facility;

/**
 * Contract CanonicalFacilityCollectionItem.
 *
 * Carries a canonical collection row without loading an ORM graph.
 *
 * @category Contract
 */
final readonly class CanonicalFacilityCollectionItem
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * @param array<string, mixed> $metadata the retained metadata
   * @param list<array{id: string, name: string, type: string}> $path the ancestor path
   * @param list<string> $hierarchyIssues the hierarchy diagnostics
   */
  public function __construct(
    public string $id,
    public string $organizationId,
    public ?string $interventionId,
    public string $recordStatus,
    public int $revision,
    public ?string $parentFacilityId,
    public bool $hasChildren,
    public string $type,
    public string $name,
    public ?string $code,
    public string $status,
    public ?string $address,
    public ?float $latitude,
    public ?float $longitude,
    public array $metadata,
    public ?int $levelIndex,
    public ?float $elevationMeters,
    public ?float $heightMeters,
    public ?string $customerId,
    public string $createdAt,
    public string $updatedAt,
    public array $path = [],
    public array $hierarchyIssues = [],
  ) {
  }
  // #endregion
}
