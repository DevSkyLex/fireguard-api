<?php

declare(strict_types=1);

namespace Procurement\Presentation\Api\Dto\Output;

use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Serializer\Attribute\Groups;

/** Physical supply return fields never contain internal monetary values. */
final class ProcurementReturnOutput
{
  #[ApiProperty(identifier: true)]
  #[Groups(['procurement:read'])]
  public string $id;

  #[Groups(['procurement:read'])]
  public string $organizationId;

  #[Groups(['procurement:read'])]
  public string $receiptId;

  #[Groups(['procurement:read'])]
  public string $clientOperationId;

  #[Groups(['procurement:read'])]
  public string $quantity;

  #[Groups(['procurement:read'])]
  public string $reason;

  #[Groups(['procurement:read'])]
  public string $status;

  #[Groups(['procurement:read'])]
  public ?string $inventoryMovementId;

  #[Groups(['procurement:read'])]
  public ?string $blockedReason;

  #[Groups(['procurement:read'])]
  public string $createdAt;

  #[Groups(['procurement:read'])]
  public ?string $reconciledAt;

  #[Groups(['procurement:read'])]
  public int $revision;

  #[Groups(['procurement:read'])]
  public bool $replayed = false;

  /**
   * @param array<string,mixed> $data
   */
  public static function fromProjection(array $data, bool $replayed = false): self
  {
    /** @var array{id:string,organizationId:string,receiptId:string,clientOperationId:string,quantity:string,reason:string,status:string,inventoryMovementId:string|null,blockedReason:string|null,createdAt:string,reconciledAt:string|null,revision:int} $data */
    $output = new self();
    $output->id = $data['id'];
    $output->organizationId = $data['organizationId'];
    $output->receiptId = $data['receiptId'];
    $output->clientOperationId = $data['clientOperationId'];
    $output->quantity = $data['quantity'];
    $output->reason = $data['reason'];
    $output->status = $data['status'];
    $output->inventoryMovementId = $data['inventoryMovementId'];
    $output->blockedReason = $data['blockedReason'];
    $output->createdAt = $data['createdAt'];
    $output->reconciledAt = $data['reconciledAt'];
    $output->revision = $data['revision'];
    $output->replayed = $replayed;

    return $output;
  }
}
