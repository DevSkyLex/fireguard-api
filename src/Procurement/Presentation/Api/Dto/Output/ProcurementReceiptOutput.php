<?php

declare(strict_types=1);

namespace Procurement\Presentation\Api\Dto\Output;

use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Serializer\Attribute\Groups;

use function array_key_exists;
use function is_string;

/** Authorized procurement projection; internal costs are absent without financial permission. */
final class ProcurementReceiptOutput
{
  #[Groups(['procurement:read'])]
  public string $pendingReturnQuantity = '0.000000';

  #[ApiProperty(identifier: true)]
  #[Groups(['procurement:read'])]
  public string $id;

  #[Groups(['procurement:read'])]
  public string $organizationId;

  #[Groups(['procurement:read'])]
  public string $orderId;

  #[Groups(['procurement:read'])]
  public string $lineId;

  #[Groups(['procurement:read'])]
  public string $kind;

  #[Groups(['procurement:read'])]
  public string $quantity;

  #[Groups(['procurement:read'])]
  public ?string $warehouseId;

  #[Groups(['procurement:read'])]
  public string $currency;

  #[Groups(['procurement:read'])]
  public string $receivedAt;

  #[Groups(['procurement:read'])]
  public string $createdAt;

  #[Groups(['procurement:read'])]
  public ?string $inventoryMovementId;

  /**
   * @var list<string>
   */
  #[Groups(['procurement:read'])]
  public array $equipmentIds;

  #[Groups(['procurement:read'])]
  public string $returnedQuantity;

  #[Groups(['procurement:read'])]
  public string $status;

  #[Groups(['procurement:read'])]
  public ?string $blockedReason;

  #[Groups(['procurement:read'])]
  public int $revision;

  #[Groups(['procurement:read'])]
  public bool $financialVisible;

  #[Groups(['procurement:read'])]
  public bool $replayed = false;

  #[Groups(['procurement:read'])]
  public ?string $unitCost;

  /**
   * @param array<string,mixed> $data
   */
  public static function fromProjection(array $data, bool $replayed = false): self
  {
    /** @var array{id:string,organizationId:string,orderId:string,lineId:string,kind:string,quantity:string,warehouseId:string|null,currency:string,receivedAt:string,createdAt:string,inventoryMovementId:string|null,equipmentIds:list<string>,returnedQuantity:string,status:string,blockedReason:string|null,revision:int,financialVisible:bool,unitCost?:string|null,pendingReturnQuantity?:string} $data */
    $output = new self();
    $output->pendingReturnQuantity = $data['pendingReturnQuantity'] ?? '0.000000';
    $output->id = $data['id'];
    $output->organizationId = $data['organizationId'];
    $output->orderId = $data['orderId'];
    $output->lineId = $data['lineId'];
    $output->kind = $data['kind'];
    $output->quantity = $data['quantity'];
    $output->warehouseId = $data['warehouseId'];
    $output->currency = $data['currency'];
    $output->receivedAt = $data['receivedAt'];
    $output->createdAt = $data['createdAt'];
    $output->inventoryMovementId = $data['inventoryMovementId'];
    $output->equipmentIds = $data['equipmentIds'];
    $output->returnedQuantity = $data['returnedQuantity'];
    $output->status = $data['status'];
    $output->blockedReason = $data['blockedReason'];
    $output->revision = $data['revision'];
    $output->financialVisible = $data['financialVisible'];
    if (array_key_exists('unitCost', $data)) {
      $output->unitCost = is_string($data['unitCost']) ? $data['unitCost'] : null;
    }
    $output->replayed = $replayed;

    return $output;
  }
}
