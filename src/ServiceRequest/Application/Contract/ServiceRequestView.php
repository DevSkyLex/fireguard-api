<?php

declare(strict_types=1);

namespace ServiceRequest\Application\Contract;

use DateTimeImmutable;

/** Class ServiceRequestView. Minimal retained target identity without customer contacts. @category Contract */
final readonly class ServiceRequestView
{
  /**
   * @param array{equipment:?array{id:string,name:?string,assetCode:?string,status:?string},site:?array{id:string,name:string},customer:?array{id:string,name:string}} $targetSnapshot
   */
  public function __construct(
    public string $id,
    public string $organizationId,
    public ?string $equipmentId,
    public ?string $siteId,
    public string $title,
    public string $description,
    public string $priority,
    public string $status,
    public ?string $originInspectionId,
    public ?string $originNonConformityId,
    public ?string $qualificationNote,
    public ?string $decisionReason,
    public ?string $interventionId,
    public ?string $taskId,
    public DateTimeImmutable $requestedAt,
    public DateTimeImmutable $updatedAt,
    public ?DateTimeImmutable $qualifiedAt,
    public ?DateTimeImmutable $rejectedAt,
    public ?DateTimeImmutable $cancelledAt,
    public ?DateTimeImmutable $convertedAt,
    public int $revision,
    public array $targetSnapshot,
  ) {
  }


}
