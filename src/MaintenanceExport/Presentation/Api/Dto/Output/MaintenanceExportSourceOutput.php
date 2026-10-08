<?php

declare(strict_types=1);

namespace MaintenanceExport\Presentation\Api\Dto\Output;

use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Class MaintenanceExportSourceOutput
 * Exposes authorized metadata without contacts or artifact contents.
 *
 * @category Output
 */
final class MaintenanceExportSourceOutput
{
  // #region Properties
  #[ApiProperty(identifier: true)]
  #[Groups(['maintenance_export:read'])]
  public string $id;

  #[Groups(['maintenance_export:read'])]
  public int $number;

  #[Groups(['maintenance_export:read'])]
  public string $name;

  #[Groups(['maintenance_export:read'])]
  public string $type;

  #[Groups(['maintenance_export:read'])]
  public ?string $publishedAt;

  #[Groups(['maintenance_export:read'])]
  public ?string $publicationId;

  /**
   * @var array{id:string,name:string}|null retained structured metadata
   */
  #[Groups(['maintenance_export:read'])]
  public ?array $site;

  /**
   * @var array{id:string,name:string}|null retained structured metadata
   */
  #[Groups(['maintenance_export:read'])]
  public ?array $customer;

  #[Groups(['maintenance_export:read'])]
  public string $snapshotState;

  #[Groups(['maintenance_export:read'])]
  public bool $identityComplete;

  #[Groups(['maintenance_export:read'])]
  public bool $ready;

  #[Groups(['maintenance_export:read'])]
  public ?string $blockedReason;

  // #endregion

  // #region Methods
  /**
   * Method fromProjection
   *
   * @param array<string,mixed> $data authorized read projection
   *
   * @return self transport output
   */
  public static function fromProjection(array $data): self
  {
    /**
     * @var array{id:string,number:int,name:string,type:string,publishedAt:string|null,publicationId:string|null,site:array{id:string,name:string}|null,customer:array{id:string,name:string}|null,snapshotState:string,identityComplete:bool,ready:bool,blockedReason:string|null} $data
     */
    $output = new self();
    $output->id = $data['id'];
    $output->number = $data['number'];
    $output->name = $data['name'];
    $output->type = $data['type'];
    $output->publishedAt = $data['publishedAt'];
    $output->publicationId = $data['publicationId'];
    $output->site = $data['site'];
    $output->customer = $data['customer'];
    $output->snapshotState = $data['snapshotState'];
    $output->identityComplete = $data['identityComplete'];
    $output->ready = $data['ready'];
    $output->blockedReason = $data['blockedReason'];

    return $output;
  }
  // #endregion
}
