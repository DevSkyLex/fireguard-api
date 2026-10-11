<?php

declare(strict_types=1);

namespace ServiceRequest\Application\Service;

use ServiceRequest\Application\Contract\ServiceRequestView;
use ServiceRequest\Domain\Model\ServiceRequest\ServiceRequest;

use function is_array;
use function is_string;

/**
 * Service ServiceRequestViewMapper.
 *
 * Builds the public read projection from the owning aggregate.
 *
 * @category Service
 */
final class ServiceRequestViewMapper
{
  public static function fromRequest(ServiceRequest $request): ServiceRequestView
  {
    return new ServiceRequestView($request->id, $request->organizationId, $request->equipmentId, $request->siteId, $request->title, $request->description, $request->priority, $request->status, $request->originInspectionId, $request->originNonConformityId, $request->qualificationNote, $request->decisionReason, $request->interventionId, $request->taskId, $request->requestedAt, $request->updatedAt, $request->qualifiedAt, $request->rejectedAt, $request->cancelledAt, $request->convertedAt, $request->revision, self::snapshot($request->targetSnapshot));
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
