<?php

declare(strict_types=1);

namespace Procurement\Presentation\Api\Dto\Output;

use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Serializer\Attribute\Groups;

/** Authorized procurement projection; internal costs are absent without financial permission. */
final class PurchaseOrderOutput
{
  #[ApiProperty(identifier: true)]
  #[Groups(['procurement:read'])]
  public string $id;

  #[Groups(['procurement:read'])]
  public string $organizationId;

  #[Groups(['procurement:read'])]
  public string $supplierId;

  #[Groups(['procurement:read'])]
  public string $name;

  #[Groups(['procurement:read'])]
  public string $currency;

  #[Groups(['procurement:read'])]
  public string $status;

  /**
   * @var list<array<string,mixed>>|list<string>
   */
  #[Groups(['procurement:read'])]
  public array $lines;

  #[Groups(['procurement:read'])]
  public bool $financialVisible;

  #[Groups(['procurement:read'])]
  public int $revision;

  #[Groups(['procurement:read'])]
  public string $createdAt;

  #[Groups(['procurement:read'])]
  public string $updatedAt;

  #[Groups(['procurement:read'])]
  public bool $replayed = false;

  /**
   * @param array<string,mixed> $data
   */
  public static function fromProjection(array $data, bool $replayed = false): self
  {
    /** @var array{id:string,organizationId:string,supplierId:string,name:string,currency:string,status:string,lines:list<array<string,mixed>>|list<string>,financialVisible:bool,revision:int,createdAt:string,updatedAt:string} $data */
    $output = new self();
    $output->id = $data['id'];
    $output->organizationId = $data['organizationId'];
    $output->supplierId = $data['supplierId'];
    $output->name = $data['name'];
    $output->currency = $data['currency'];
    $output->status = $data['status'];
    $output->lines = $data['lines'];
    $output->financialVisible = $data['financialVisible'];
    $output->revision = $data['revision'];
    $output->createdAt = $data['createdAt'];
    $output->updatedAt = $data['updatedAt'];
    $output->replayed = $replayed;

    return $output;
  }
}
