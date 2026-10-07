<?php

declare(strict_types=1);

namespace ServiceRequest\Application\Port\Outbound;

use ServiceRequest\Domain\Model\ServiceRequest\ServiceRequest;
use ServiceRequest\Domain\ValueObject\ServiceRequestConversionReceipt;

/**
 * Interface ServiceRequestRepositoryPort
 *
 * Persists scoped requests and their single conversion receipt on the main connection.
 *
 * @category Port
 */
interface ServiceRequestRepositoryPort
{
  public function find(string $id, string $organizationId, bool $lock = false): ?ServiceRequest;

  public function save(ServiceRequest $request, ?int $expectedRevision = null): void;

  /**
   * @return list<ServiceRequest>
   */
  public function list(string $organizationId, ?string $status, ?string $equipmentId, ?string $siteId, string $search, int $offset, int $limit): array;

  public function count(string $organizationId, ?string $status, ?string $equipmentId, ?string $siteId, string $search): int;

  public function conversionReceiptForRequest(string $requestId, string $organizationId): ?ServiceRequestConversionReceipt;

  public function conversionReceiptByOperation(string $organizationId, string $clientOperationId): ?ServiceRequestConversionReceipt;

  public function saveConversionReceipt(ServiceRequestConversionReceipt $receipt): void;
}
