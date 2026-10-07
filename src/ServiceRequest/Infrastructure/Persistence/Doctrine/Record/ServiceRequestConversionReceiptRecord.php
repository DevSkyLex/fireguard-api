<?php

declare(strict_types=1);

namespace ServiceRequest\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Durable request conversion receipt without cascades to retained intervention history.
 *
 * @category Record
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ORM\Entity]
#[ORM\Table(name: 'service_request_conversion_receipts')]
#[ORM\UniqueConstraint(name: 'uniq_service_request_conversion_operation', columns: ['organization_id', 'client_operation_id'])]
class ServiceRequestConversionReceiptRecord
{
  #[ORM\Id]
  #[ORM\Column(name: 'request_id', type: 'string', length: 36)]
  public string $requestId;

  #[ORM\Column(name: 'organization_id', type: 'string', length: 36)]
  public string $organizationId;

  #[ORM\Column(name: 'client_operation_id', type: 'string', length: 36)]
  public string $clientOperationId;

  #[ORM\Column(name: 'payload_hash', type: 'string', length: 64)]
  public string $payloadHash;

  #[ORM\Column(name: 'intervention_id', type: 'string', length: 36)]
  public string $interventionId;

  #[ORM\Column(name: 'task_id', type: 'string', length: 36)]
  public string $taskId;

  #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
  public DateTimeImmutable $createdAt;
}
