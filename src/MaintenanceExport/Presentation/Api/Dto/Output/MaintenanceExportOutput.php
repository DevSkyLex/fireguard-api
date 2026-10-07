<?php

declare(strict_types=1);

namespace MaintenanceExport\Presentation\Api\Dto\Output;

use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Class MaintenanceExportOutput
 * Exposes authorized metadata without contacts or artifact contents.
 *
 * @category Output
 */
final class MaintenanceExportOutput
{
  // #region Properties
  #[ApiProperty(identifier: true)]
  #[Groups(['maintenance_export:read'])]
  public string $id;

  #[Groups(['maintenance_export:read'])]
  public string $organizationId;

  #[Groups(['maintenance_export:read'])]
  public string $actorId;

  #[Groups(['maintenance_export:read'])]
  public string $kind;

  #[Groups(['maintenance_export:read'])]
  public int $schemaVersion;

  #[Groups(['maintenance_export:read'])]
  public string $system;

  #[Groups(['maintenance_export:read'])]
  public bool $includeInternalCosts;

  /**
   * @var list<string> retained structured metadata
   */
  #[Groups(['maintenance_export:read'])]
  public array $sourceInterventionIds;

  #[Groups(['maintenance_export:read'])]
  public ?string $originalExportId;

  #[Groups(['maintenance_export:read'])]
  public ?string $adjustmentOf;

  #[Groups(['maintenance_export:read'])]
  public ?string $reason;

  #[Groups(['maintenance_export:read'])]
  public string $createdAt;

  #[Groups(['maintenance_export:read'])]
  public int $revision;

  #[Groups(['maintenance_export:read'])]
  public string $state;

  /**
   * @var array<string,mixed>|null retained structured metadata
   */
  #[Groups(['maintenance_export:read'])]
  public ?array $confirmation;

  #[Groups(['maintenance_export:read'])]
  public int $rowCount;

  #[Groups(['maintenance_export:read'])]
  public ?bool $costsComplete;

  #[Groups(['maintenance_export:read'])]
  public ?int $incompleteCostCount;

  /**
   * @var array<string,array{mediaType:string,sha256:string,size:int}> retained structured metadata
   */
  #[Groups(['maintenance_export:read'])]
  public array $files;

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
     * @var array{id:string,organizationId:string,actorId:string,kind:string,schemaVersion:int,system:string,includeInternalCosts:bool,sourceInterventionIds:list<string>,originalExportId:string|null,adjustmentOf:string|null,reason:string|null,createdAt:string,revision:int,state:string,confirmation:array<string,mixed>|null,rowCount:int,costsComplete:bool|null,incompleteCostCount:int|null,files:array<string,array{mediaType:string,sha256:string,size:int}>} $data
     */
    $output = new self();
    $output->id = $data['id'];
    $output->organizationId = $data['organizationId'];
    $output->actorId = $data['actorId'];
    $output->kind = $data['kind'];
    $output->schemaVersion = $data['schemaVersion'];
    $output->system = $data['system'];
    $output->includeInternalCosts = $data['includeInternalCosts'];
    $output->sourceInterventionIds = $data['sourceInterventionIds'];
    $output->originalExportId = $data['originalExportId'];
    $output->adjustmentOf = $data['adjustmentOf'];
    $output->reason = $data['reason'];
    $output->createdAt = $data['createdAt'];
    $output->revision = $data['revision'];
    $output->state = $data['state'];
    $output->confirmation = $data['confirmation'];
    $output->rowCount = $data['rowCount'];
    $output->costsComplete = $data['costsComplete'];
    $output->incompleteCostCount = $data['incompleteCostCount'];
    $output->files = $data['files'];
    $output->replayed = $replayed;

    return $output;
  }
  // #endregion
}
