<?php

declare(strict_types=1);

namespace Facility\Presentation\Api\Dto\Output\Model;

use ApiPlatform\Metadata\ApiProperty;
use Facility\Application\Contract\Model\FacilityModelView;
use Facility\Presentation\Api\Serialization\FacilitySerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

use function count;

use const DATE_ATOM;

/**
 * DTO FacilityModelOutput.
 *
 * @category DTO
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[Groups([FacilitySerializationGroup::READ])]
final readonly class FacilityModelOutput
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
   * @param string $mimeType the mime type
   * @param int $fileSize the file size
   * @param int $nodeCount the node count
   * @param list<array{index: int, name: string}> $nodes
   * @param int $revision the revision
   * @param bool $active the active
   * @param array{scale: float, rotationDegrees: float, translation: array{x: float, y: float, z: float}} $transform
   * @param list<array{nodeIndex: int, facilityId: string}> $bindings
   * @param string $downloadUrl the download url
   * @param string $createdAt the created at
   * @param string $updatedAt the updated at
   * @param list<array{nodeIndex: int, code: 'target_unavailable'}> $bindingIssues the unavailable node associations
   *
   * @return void no return value
   */
  public function __construct(
    #[ApiProperty(identifier: true)]
    public string $id,
    public string $organizationId,
    public string $buildingId,
    public string $fileName,
    public string $mimeType,
    public int $fileSize,
    public int $nodeCount,
    public array $nodes,
    public int $revision,
    public bool $active,
    public array $transform,
    public array $bindings,
    public string $downloadUrl,
    public string $createdAt,
    public string $updatedAt,
    #[ApiProperty(openapiContext: [
      'type' => 'array',
      'items' => ['type' => 'object', 'required' => ['nodeIndex', 'code'], 'additionalProperties' => false,
        'properties' => ['nodeIndex' => ['type' => 'integer', 'minimum' => 0], 'code' => ['type' => 'string', 'enum' => ['target_unavailable']]]],
    ])]
    public array $bindingIssues = [],
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method fromView.
   *
   * Maps model contract fields into the API output.
   *
   * @access public
   * @since 1.0.0
   *
   * @param FacilityModelView $model the model
   *
   * @return self the operation result
   */
  public static function fromView(FacilityModelView $model): self
  {
    return new self(
      $model->id,
      $model->organizationId,
      $model->buildingId,
      $model->fileName,
      'model/gltf-binary',
      $model->fileSize,
      count($model->nodes),
      $model->nodes,
      $model->revision,
      $model->active,
      $model->transform,
      $model->bindings,
      '/api/facility-models/' . $model->id . '/download',
      $model->createdAt->format(DATE_ATOM),
      $model->updatedAt->format(DATE_ATOM),
      $model->bindingIssues,
    );
  }
  // #endregion
}
