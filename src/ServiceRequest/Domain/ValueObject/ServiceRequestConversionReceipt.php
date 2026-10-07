<?php

declare(strict_types=1);

namespace ServiceRequest\Domain\ValueObject;

use DateTimeImmutable;
use ServiceRequest\Domain\Exception\ServiceRequestException;
use Shared\Domain\Exception\InvalidValueException;
use Shared\Domain\ValueObject\Uuid;

use function preg_match;

/**
 * Immutable receipt for replaying one request's committed conversion.
 *
 * @category ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ServiceRequestConversionReceipt
{
  public function __construct(public string $organizationId, public string $requestId, public string $clientOperationId, public string $payloadHash, public string $interventionId, public string $taskId, public DateTimeImmutable $createdAt)
  {
    try {
      foreach ([$organizationId, $requestId, $clientOperationId, $interventionId, $taskId] as $id) {
        new Uuid($id);
      }
    } catch (InvalidValueException) {
      throw ServiceRequestException::invalid('Invalid service request conversion identifier.');
    }
    if (1 !== preg_match('/^[a-f0-9]{64}$/D', $payloadHash)) {
      throw ServiceRequestException::invalid('Invalid service request conversion fingerprint.');
    }
  }
}
