<?php

declare(strict_types=1);

namespace Facility\Domain\Model\FacilityModel;

use DateTimeImmutable;
use Facility\Domain\Exception\FacilityModelException;
use Facility\Domain\ValueObject\{FacilityId, FacilityModelTransform, FacilityOrganizationId};
use Shared\Domain\ValueObject\Uuid;

use function array_is_list;
use function array_key_exists;
use function count;
use function is_array;
use function is_int;
use function is_string;
use function mb_strlen;
use function str_replace;
use function trim;

/**
 * Model FacilityModel. Immutable GLB content and independently revisioned settings.
 *
 * @category Model
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class FacilityModel
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Initializes the model capability with its typed dependencies and state.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $id the id
   * @param string $organizationId the organization id
   * @param string $buildingId the building id
   * @param string $fileName the file name
   * @param string $storagePath the storage path
   * @param int $fileSize the file size
   * @param list<array{index: int, name: string}> $nodes
   * @param int $revision the revision
   * @param bool $active the active
   * @param FacilityModelTransform $transform the transform
   * @param list<array{nodeIndex: int, facilityId: string}> $bindings
   * @param DateTimeImmutable $createdAt the created at
   * @param DateTimeImmutable $updatedAt the updated at
   *
   * @return void no return value
   */
  public function __construct(
    public readonly string $id,
    public readonly string $organizationId,
    public readonly string $buildingId,
    public readonly string $fileName,
    public readonly string $storagePath,
    public readonly int $fileSize,
    public readonly array $nodes,
    public private(set) int $revision,
    public private(set) bool $active,
    public private(set) FacilityModelTransform $transform,
    public private(set) array $bindings,
    public readonly DateTimeImmutable $createdAt,
    public private(set) DateTimeImmutable $updatedAt,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method create.
   *
   * Creates a draft model with immutable file metadata and independent settings.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $id the id
   * @param string $organizationId the organization id
   * @param string $buildingId the building id
   * @param string $fileName the file name
   * @param int $fileSize the file size
   * @param list<array{index: int, name: string}> $nodes
   *
   * @return self the operation result
   */
  public static function create(string $id, string $organizationId, string $buildingId, string $fileName, int $fileSize, array $nodes): self
  {
    new Uuid($id);
    FacilityOrganizationId::fromString($organizationId);
    FacilityId::fromString($buildingId);
    $fileName = trim(str_replace(['/', '\\', "\r", "\n", "\0"], '_', $fileName));
    if ('' === $fileName || mb_strlen($fileName) > 255) {
      throw FacilityModelException::invalid('The GLB filename must contain between 1 and 255 characters.');
    }
    $now = new DateTimeImmutable();

    return new self(
      $id,
      $organizationId,
      $buildingId,
      $fileName,
      'facility-model/' . $buildingId . '/' . $id . '/model.glb',
      $fileSize,
      $nodes,
      1,
      false,
      new FacilityModelTransform(),
      [],
      $now,
      $now,
    );
  }

  /**
   * Method changeSettings.
   *
   * Replaces the transformation and validates unique node associations before revising the model.
   *
   * @access public
   * @since 1.0.0
   *
   * @param FacilityModelTransform $transform the transform
   * @param list<mixed> $bindings
   * @param array<array-key, mixed> $removeBindingNodeIndices explicitly removed node associations
   *
   * @return void no return value
   */
  public function changeSettings(FacilityModelTransform $transform, array $bindings, array $removeBindingNodeIndices = []): void
  {
    if (!array_is_list($removeBindingNodeIndices)) {
      throw FacilityModelException::invalid('Removed associations must be a list of node indices.');
    }
    $removed = [];
    foreach ($removeBindingNodeIndices as $index) {
      if (!is_int($index) || $index < 0 || $index >= count($this->nodes) || isset($removed[$index])) {
        throw FacilityModelException::invalid('Removed associations must reference unique existing node indices.');
      }
      $removed[$index] = true;
    }
    $seen = [];
    $validated = [];
    foreach ($bindings as $binding) {
      if (!is_array($binding) || !is_int($binding['nodeIndex'] ?? null)
        || !is_string($binding['facilityId'] ?? null)) {
        throw FacilityModelException::invalid('Each model association requires a nodeIndex and a facilityId.');
      }
      $index = $binding['nodeIndex'];
      if (isset($removed[$index])) {
        throw FacilityModelException::invalid('A node association cannot be removed and assigned together.');
      }
      if ($index < 0 || $index >= count($this->nodes) || array_key_exists($index, $seen)) {
        throw FacilityModelException::invalid('An association must reference an existing node once only.');
      }
      FacilityId::fromString($binding['facilityId']);
      $seen[$index] = true;
      $validated[] = ['nodeIndex' => $index, 'facilityId' => $binding['facilityId']];
    }
    $this->transform = $transform;
    $this->bindings = $validated;
    $this->touch();
  }

  /**
   * Method activate.
   *
   * Activates the selected model while preserving revision preconditions.
   *
   * @access public
   * @since 1.0.0
   *
   * @return void no return value
   */
  public function activate(): void
  {
    $this->active = true;
    $this->touch();
  }

  /**
   * Method touch.
   *
   * Advances the model revision after a successful state change.
   *
   * @access private
   * @since 1.0.0
   *
   * @return void no return value
   */
  private function touch(): void
  {
    ++$this->revision;
    $this->updatedAt = new DateTimeImmutable();
  }
  // #endregion
}
