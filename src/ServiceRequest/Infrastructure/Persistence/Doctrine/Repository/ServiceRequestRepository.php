<?php

declare(strict_types=1);

namespace ServiceRequest\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use ServiceRequest\Application\Port\Outbound\ServiceRequestRepositoryPort;
use ServiceRequest\Domain\Exception\ServiceRequestException;
use ServiceRequest\Domain\Model\ServiceRequest\ServiceRequest;
use ServiceRequest\Domain\ValueObject\ServiceRequestConversionReceipt;

use function array_map;
use function json_decode;
use function json_encode;
use function str_replace;
use function trim;

use const JSON_PRESERVE_ZERO_FRACTION;
use const JSON_THROW_ON_ERROR;

/**
 * Organization-scoped main persistence, row locks, revisions and conversion receipts.
 *
 * @category Repository
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ServiceRequestRepository implements ServiceRequestRepositoryPort
{
  public function __construct(private EntityManagerInterface $entityManager)
  {
  }

  public function find(string $id, string $organizationId, bool $lock = false): ?ServiceRequest
  {
    $connection = $this->entityManager->getConnection();
    if ($lock && !$connection->isTransactionActive()) {
      throw new LogicException('A service request row lock requires an active main transaction.');
    }
    $row = $connection->fetchAssociative('SELECT * FROM service_requests WHERE id = :id AND organization_id = :organization' . ($lock ? ' FOR UPDATE' : ''), ['id' => $id, 'organization' => $organizationId]);

    return false === $row ? null : $this->map($row);
  }

  public function save(ServiceRequest $request, ?int $expectedRevision = null): void
  {
    $connection = $this->entityManager->getConnection();
    $values = [
      'organization_id' => $request->organizationId,
      'equipment_id' => $request->equipmentId,
      'site_id' => $request->siteId,
      'target_snapshot' => json_encode($request->targetSnapshot, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION),
      'title' => $request->title,
      'description' => $request->description,
      'priority' => $request->priority,
      'origin_inspection_id' => $request->originInspectionId,
      'origin_non_conformity_id' => $request->originNonConformityId,
      'status' => $request->status,
      'revision' => $request->revision,
      'requested_at' => self::format($request->requestedAt),
      'updated_at' => self::format($request->updatedAt),
      'qualified_at' => self::format($request->qualifiedAt),
      'rejected_at' => self::format($request->rejectedAt),
      'cancelled_at' => self::format($request->cancelledAt),
      'converted_at' => self::format($request->convertedAt),
      'decision_reason' => $request->decisionReason,
      'qualification_note' => $request->qualificationNote,
      'intervention_id' => $request->interventionId,
      'task_id' => $request->taskId,
    ];

    try {
      if (null === $expectedRevision) {
        $connection->insert('service_requests', ['id' => $request->id] + $values);
      } elseif (1 !== $connection->update('service_requests', $values, ['id' => $request->id, 'organization_id' => $request->organizationId, 'revision' => $expectedRevision])) {
        throw ServiceRequestException::stale();
      }
    } catch (UniqueConstraintViolationException) {
      throw ServiceRequestException::operationConflict('A service request already exists with this identifier.');
    }
  }

  /**
   * @return list<ServiceRequest>
   */
  public function list(string $organizationId, ?string $status, ?string $equipmentId, ?string $siteId, string $search, int $offset, int $limit): array
  {
    $criteria = self::criteria($organizationId, $status, $equipmentId, $siteId, $search);
    $rows = $this->entityManager->getConnection()->fetchAllAssociative('SELECT * FROM service_requests WHERE ' . $criteria['sql'] . ' ORDER BY requested_at DESC, id ASC LIMIT :limit OFFSET :offset', $criteria['parameters'] + ['limit' => $limit, 'offset' => $offset], ['limit' => ParameterType::INTEGER, 'offset' => ParameterType::INTEGER]);

    return array_map($this->map(...), $rows);
  }

  public function count(string $organizationId, ?string $status, ?string $equipmentId, ?string $siteId, string $search): int
  {
    $criteria = self::criteria($organizationId, $status, $equipmentId, $siteId, $search);
    /** @var int|string $count */
    $count = $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM service_requests WHERE ' . $criteria['sql'], $criteria['parameters']);

    return (int) $count;
  }

  public function conversionReceiptForRequest(string $requestId, string $organizationId): ?ServiceRequestConversionReceipt
  {
    $row = $this->entityManager->getConnection()->fetchAssociative('SELECT * FROM service_request_conversion_receipts WHERE request_id = :request AND organization_id = :organization', ['request' => $requestId, 'organization' => $organizationId]);

    return false === $row ? null : self::receipt($row);
  }

  public function conversionReceiptByOperation(string $organizationId, string $clientOperationId): ?ServiceRequestConversionReceipt
  {
    $row = $this->entityManager->getConnection()->fetchAssociative('SELECT * FROM service_request_conversion_receipts WHERE organization_id = :organization AND client_operation_id = :operation', ['organization' => $organizationId, 'operation' => $clientOperationId]);

    return false === $row ? null : self::receipt($row);
  }

  public function saveConversionReceipt(ServiceRequestConversionReceipt $receipt): void
  {
    try {
      $inserted = $this->entityManager->getConnection()->executeStatement("INSERT INTO service_request_conversion_receipts (request_id, organization_id, client_operation_id, payload_hash, intervention_id, task_id, created_at) SELECT id, organization_id, :operation, :hash, :intervention, :task, :created FROM service_requests WHERE id = :request AND organization_id = :organization AND status = 'converted' AND intervention_id = :intervention AND task_id = :task", [
        'operation' => $receipt->clientOperationId,
        'hash' => $receipt->payloadHash,
        'intervention' => $receipt->interventionId,
        'task' => $receipt->taskId,
        'created' => self::format($receipt->createdAt),
        'request' => $receipt->requestId,
        'organization' => $receipt->organizationId,
      ]);
      if (1 !== $inserted) {
        throw ServiceRequestException::operationConflict('A conversion receipt must match its converted request and organization.');
      }
    } catch (UniqueConstraintViolationException) {
      throw ServiceRequestException::operationConflict();
    }
  }

  /**
   * @return array{sql:string,parameters:array<string,string>}
   */
  private static function criteria(string $organizationId, ?string $status, ?string $equipmentId, ?string $siteId, string $search): array
  {
    $sql = 'organization_id = :organization';
    $parameters = ['organization' => $organizationId];
    foreach (['status' => $status, 'equipment_id' => $equipmentId, 'site_id' => $siteId] as $column => $value) {
      if (null !== $value) {
        $sql .= ' AND ' . $column . ' = :' . $column;
        $parameters[$column] = $value;
      }
    }
    $search = trim($search);
    if ('' !== $search) {
      $sql .= " AND (title ILIKE :search ESCAPE '\\' OR description ILIKE :search ESCAPE '\\')";
      $parameters['search'] = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search) . '%';
    }

    return ['sql' => $sql, 'parameters' => $parameters];
  }

  /**
   * @param array<string,mixed> $row persisted request
   */
  private function map(array $row): ServiceRequest
  {
    /** @var array{id:string,organization_id:string,equipment_id:?string,site_id:?string,target_snapshot:string,title:string,description:string,priority:string,origin_inspection_id:?string,origin_non_conformity_id:?string,status:string,revision:int|string,requested_at:string,updated_at:string,qualified_at:?string,rejected_at:?string,cancelled_at:?string,converted_at:?string,decision_reason:?string,qualification_note:?string,intervention_id:?string,task_id:?string} $row */
    /** @var array<string,mixed> $snapshot */
    $snapshot = json_decode($row['target_snapshot'], true, 512, JSON_THROW_ON_ERROR);

    return ServiceRequest::reconstitute(
      id: $row['id'],
      organizationId: $row['organization_id'],
      equipmentId: $row['equipment_id'],
      siteId: $row['site_id'],
      targetSnapshot: $snapshot,
      title: $row['title'],
      description: $row['description'],
      priority: $row['priority'],
      originInspectionId: $row['origin_inspection_id'],
      originNonConformityId: $row['origin_non_conformity_id'],
      status: $row['status'],
      revision: (int) $row['revision'],
      requestedAt: new DateTimeImmutable($row['requested_at'], new DateTimeZone('UTC')),
      updatedAt: new DateTimeImmutable($row['updated_at'], new DateTimeZone('UTC')),
      qualifiedAt: self::date($row['qualified_at']),
      rejectedAt: self::date($row['rejected_at']),
      cancelledAt: self::date($row['cancelled_at']),
      convertedAt: self::date($row['converted_at']),
      decisionReason: $row['decision_reason'],
      qualificationNote: $row['qualification_note'],
      interventionId: $row['intervention_id'],
      taskId: $row['task_id'],
    );
  }

  /**
   * @param array<string,mixed> $row persisted conversion receipt
   */
  private static function receipt(array $row): ServiceRequestConversionReceipt
  {
    /** @var array{organization_id:string,request_id:string,client_operation_id:string,payload_hash:string,intervention_id:string,task_id:string,created_at:string} $row */
    return new ServiceRequestConversionReceipt($row['organization_id'], $row['request_id'], $row['client_operation_id'], $row['payload_hash'], $row['intervention_id'], $row['task_id'], new DateTimeImmutable($row['created_at'], new DateTimeZone('UTC')));
  }

  private static function format(?DateTimeImmutable $date): ?string
  {
    return $date?->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
  }

  private static function date(?string $date): ?DateTimeImmutable
  {
    return null === $date ? null : new DateTimeImmutable($date, new DateTimeZone('UTC'));
  }
}
