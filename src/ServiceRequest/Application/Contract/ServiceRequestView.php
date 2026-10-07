<?php

declare(strict_types=1);

namespace ServiceRequest\Application\Contract;

use DateTimeImmutable;
use ServiceRequest\Domain\Model\ServiceRequest\ServiceRequest;

use function is_array;
use function is_string;

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

  public static function fromRequest(ServiceRequest $request): self
  {
    return new self($request->id, $request->organizationId, $request->equipmentId, $request->siteId, $request->title, $request->description, $request->priority, $request->status, $request->originInspectionId, $request->originNonConformityId, $request->qualificationNote, $request->decisionReason, $request->interventionId, $request->taskId, $request->requestedAt, $request->updatedAt, $request->qualifiedAt, $request->rejectedAt, $request->cancelledAt, $request->convertedAt, $request->revision, self::snapshot($request->targetSnapshot));
  }

  /**
   * @param array<string,mixed> $snapshot
   *
   * @return array{equipment:?array{id:string,name:?string,assetCode:?string,status:?string},site:?array{id:string,name:string},customer:?array{id:string,name:string}}
   */
  private static function snapshot(array $snapshot): array
  {
    $equipment = is_array($snapshot['equipment'] ?? null) ? $snapshot['equipment'] : [];
    $site = is_array($snapshot['site'] ?? null) ? $snapshot['site'] : [];
    $customer = is_array($snapshot['customer'] ?? null) ? $snapshot['customer'] : [];

    return [
      'equipment' => is_string($equipment['id'] ?? null) ? ['id' => $equipment['id'], 'name' => self::text($equipment['name'] ?? null), 'assetCode' => self::text($equipment['assetCode'] ?? null), 'status' => self::text($equipment['status'] ?? null)] : null,
      'site' => is_string($site['id'] ?? null) && is_string($site['name'] ?? null) ? ['id' => $site['id'], 'name' => $site['name']] : null,
      'customer' => is_string($customer['id'] ?? null) && is_string($customer['name'] ?? null) ? ['id' => $customer['id'], 'name' => $customer['name']] : null,
    ];
  }

  private static function text(mixed $value): ?string
  {
    return is_string($value) ? $value : null;
  }
}
