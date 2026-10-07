<?php

declare(strict_types=1);

namespace MaintenanceExport\Presentation\Api\Dto\Output;

use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Class MaintenanceExportReferenceOutput
 * Exposes authorized metadata without contacts or artifact contents.
 *
 * @category Output
 */
final class MaintenanceExportReferenceOutput
{
  // #region Properties
  #[ApiProperty(identifier: true)]
  #[Groups(['maintenance_export:read'])]
  public string $id;

  #[Groups(['maintenance_export:read'])]
  public string $organizationId;

  #[Groups(['maintenance_export:read'])]
  public string $resourceType;

  #[Groups(['maintenance_export:read'])]
  public string $resourceId;

  #[Groups(['maintenance_export:read'])]
  public string $system;

  #[Groups(['maintenance_export:read'])]
  public string $reference;

  #[Groups(['maintenance_export:read'])]
  public int $revision;

  #[Groups(['maintenance_export:read'])]
  public string $updatedAt;

  #[Groups(['maintenance_export:read'])]
  public bool $replayed = false;

  // #endregion

  // #region Methods
  /**
   * Method fromProjection
   *
   * @param array<string,mixed> $data authorized read projection
   *
   * @return self transport output
   */
  public static function fromProjection(array $data, bool $replayed = false): self
  {
    /**
     * @var array{id:string,organizationId:string,resourceType:string,resourceId:string,system:string,reference:string,revision:int,updatedAt:string} $data
     */
    $output = new self();
    $output->id = $data['id'];
    $output->organizationId = $data['organizationId'];
    $output->resourceType = $data['resourceType'];
    $output->resourceId = $data['resourceId'];
    $output->system = $data['system'];
    $output->reference = $data['reference'];
    $output->revision = $data['revision'];
    $output->updatedAt = $data['updatedAt'];
    $output->replayed = $replayed;

    return $output;
  }
  // #endregion
}
