<?php

declare(strict_types=1);

namespace ServiceRequest\Domain\Model\ServiceRequest;

use DateTimeImmutable;
use JsonException;
use ServiceRequest\Domain\Exception\ServiceRequestException;
use Shared\Domain\Exception\InvalidValueException;
use Shared\Domain\ValueObject\Uuid;

use function array_key_exists;
use function in_array;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;
use function json_decode;
use function json_encode;
use function mb_strlen;
use function strlen;
use function trim;

use const JSON_PRESERVE_ZERO_FRACTION;
use const JSON_THROW_ON_ERROR;

/**
 * Immutable maintenance request with retained target identity and conversion history.
 *
 * @category Model
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ServiceRequest
{
  private const array PRIORITIES = ['low', 'normal', 'high', 'urgent'];

  /**
   * @param array<string,mixed> $targetSnapshot retained owner-published target identity
   */
  private function __construct(
    public string $id,
    public string $organizationId,
    public ?string $equipmentId,
    public ?string $siteId,
    public array $targetSnapshot,
    public string $title,
    public string $description,
    public string $priority,
    public ?string $originInspectionId,
    public ?string $originNonConformityId,
    public string $status,
    public int $revision,
    public DateTimeImmutable $requestedAt,
    public DateTimeImmutable $updatedAt,
    public ?DateTimeImmutable $qualifiedAt = null,
    public ?DateTimeImmutable $rejectedAt = null,
    public ?DateTimeImmutable $cancelledAt = null,
    public ?DateTimeImmutable $convertedAt = null,
    public ?string $decisionReason = null,
    public ?string $qualificationNote = null,
    public ?string $interventionId = null,
    public ?string $taskId = null,
  ) {
  }

  /**
   * @param array<string,mixed> $targetSnapshot retained equipment, site and internal customer identity
   */
  public static function create(string $id, string $organizationId, ?string $equipmentId, ?string $siteId, array $targetSnapshot, string $title, string $description, DateTimeImmutable $now, string $priority = 'normal', ?string $originInspectionId = null, ?string $originNonConformityId = null): self
  {
    self::uuid($id);
    self::uuid($organizationId);
    foreach ([$equipmentId, $siteId, $originInspectionId, $originNonConformityId] as $reference) {
      if (null !== $reference) {
        self::uuid($reference);
      }
    }
    if (null === $equipmentId && null === $siteId) {
      throw ServiceRequestException::invalid('A service request must target an equipment or a site.');
    }
    $targetSnapshot = self::snapshot($targetSnapshot);

    return new self($id, $organizationId, $equipmentId, $siteId, $targetSnapshot, self::requiredText($title, 160, 'Title'), self::requiredText($description, 10000, 'Description'), self::priority($priority), $originInspectionId, $originNonConformityId, 'requested', 1, $now, $now);
  }

  /**
   * @param array<string,mixed> $targetSnapshot persisted retained target identity
   */
  public static function reconstitute(string $id, string $organizationId, ?string $equipmentId, ?string $siteId, array $targetSnapshot, string $title, string $description, string $priority, ?string $originInspectionId, ?string $originNonConformityId, string $status, int $revision, DateTimeImmutable $requestedAt, DateTimeImmutable $updatedAt, ?DateTimeImmutable $qualifiedAt = null, ?DateTimeImmutable $rejectedAt = null, ?DateTimeImmutable $cancelledAt = null, ?DateTimeImmutable $convertedAt = null, ?string $decisionReason = null, ?string $qualificationNote = null, ?string $interventionId = null, ?string $taskId = null): self
  {
    return new self($id, $organizationId, $equipmentId, $siteId, self::snapshot($targetSnapshot), $title, $description, $priority, $originInspectionId, $originNonConformityId, $status, $revision, $requestedAt, $updatedAt, $qualifiedAt, $rejectedAt, $cancelledAt, $convertedAt, $decisionReason, $qualificationNote, $interventionId, $taskId);
  }

  /**
   * @param array<string,mixed> $changes editable request description fields
   */
  public function change(array $changes, DateTimeImmutable $now): self
  {
    $this->assertState(['requested'], $now);
    foreach ($changes as $field => $value) {
      if (!in_array($field, ['title', 'description', 'priority'], true) || !is_string($value)) {
        throw ServiceRequestException::invalid('Only title, description and priority can be changed.');
      }
    }
    /** @var array<string,string> $changes */
    $title = array_key_exists('title', $changes) ? self::requiredText($changes['title'], 160, 'Title') : $this->title;
    $description = array_key_exists('description', $changes) ? self::requiredText($changes['description'], 10000, 'Description') : $this->description;
    $priority = array_key_exists('priority', $changes) ? self::priority($changes['priority']) : $this->priority;
    if ($title === $this->title && $description === $this->description && $priority === $this->priority) {
      return $this;
    }

    return new self($this->id, $this->organizationId, $this->equipmentId, $this->siteId, $this->targetSnapshot, $title, $description, $priority, $this->originInspectionId, $this->originNonConformityId, $this->status, $this->revision + 1, $this->requestedAt, $now, $this->qualifiedAt, $this->rejectedAt, $this->cancelledAt, $this->convertedAt, $this->decisionReason, $this->qualificationNote, $this->interventionId, $this->taskId);
  }

  public function qualify(?string $note, DateTimeImmutable $now): self
  {
    $this->assertState(['requested'], $now);
    if (null === $this->equipmentId) {
      throw ServiceRequestException::invalid('Select the equipment requiring maintenance before qualifying the request.');
    }

    return $this->transition('qualified', $now, qualificationNote: self::optionalText($note, 10000, 'Qualification note'));
  }

  /**
   * @param array<string,mixed> $targetSnapshot resolved equipment identity within the original site
   */
  public function assignEquipment(string $equipmentId, ?string $siteId, array $targetSnapshot, DateTimeImmutable $now): self
  {
    $this->assertState(['requested'], $now);
    if (null !== $this->equipmentId || (null !== $this->siteId && $siteId !== $this->siteId)) {
      throw ServiceRequestException::transitionConflict('The request target cannot be replaced or moved to another site.');
    }
    self::uuid($equipmentId);
    if (null !== $siteId) {
      self::uuid($siteId);
    }

    return new self($this->id, $this->organizationId, $equipmentId, $siteId, self::snapshot($targetSnapshot), $this->title, $this->description, $this->priority, $this->originInspectionId, $this->originNonConformityId, $this->status, $this->revision + 1, $this->requestedAt, $now, $this->qualifiedAt, $this->rejectedAt, $this->cancelledAt, $this->convertedAt, $this->decisionReason, $this->qualificationNote, $this->interventionId, $this->taskId);
  }

  public function reject(string $reason, DateTimeImmutable $now): self
  {
    $this->assertEditable($now);

    return $this->transition('rejected', $now, decisionReason: self::requiredText($reason, 2000, 'Rejection reason'));
  }

  public function cancel(string $reason, DateTimeImmutable $now): self
  {
    $this->assertEditable($now);

    return $this->transition('cancelled', $now, decisionReason: self::requiredText($reason, 2000, 'Cancellation reason'));
  }

  public function convert(string $interventionId, string $taskId, DateTimeImmutable $now): self
  {
    $this->assertState(['qualified'], $now);
    self::uuid($interventionId);
    self::uuid($taskId);

    return $this->transition('converted', $now, interventionId: $interventionId, taskId: $taskId);
  }

  private function transition(string $status, DateTimeImmutable $now, ?string $decisionReason = null, ?string $qualificationNote = null, ?string $interventionId = null, ?string $taskId = null): self
  {
    return new self($this->id, $this->organizationId, $this->equipmentId, $this->siteId, $this->targetSnapshot, $this->title, $this->description, $this->priority, $this->originInspectionId, $this->originNonConformityId, $status, $this->revision + 1, $this->requestedAt, $now, 'qualified' === $status ? $now : $this->qualifiedAt, 'rejected' === $status ? $now : $this->rejectedAt, 'cancelled' === $status ? $now : $this->cancelledAt, 'converted' === $status ? $now : $this->convertedAt, $decisionReason ?? $this->decisionReason, $qualificationNote ?? $this->qualificationNote, $interventionId ?? $this->interventionId, $taskId ?? $this->taskId);
  }

  private function assertEditable(DateTimeImmutable $now): void
  {
    $this->assertState(['requested', 'qualified'], $now);
  }

  /**
   * @param list<string> $states allowed predecessor states
   */
  private function assertState(array $states, DateTimeImmutable $now): void
  {
    if (!in_array($this->status, $states, true)) {
      throw ServiceRequestException::transitionConflict();
    }
    if ($now < $this->updatedAt) {
      throw ServiceRequestException::invalid('The operation time cannot precede the last request update.');
    }
  }

  private static function uuid(string $value): void
  {
    try {
      new Uuid($value);
    } catch (InvalidValueException) {
      throw ServiceRequestException::invalid('Invalid service request identifier or reference.');
    }
  }

  private static function priority(string $value): string
  {
    if (!in_array($value, self::PRIORITIES, true)) {
      throw ServiceRequestException::invalid('Unsupported service request priority.');
    }

    return $value;
  }

  private static function requiredText(string $value, int $maximum, string $field): string
  {
    $value = trim($value);
    if ('' === $value || mb_strlen($value) > $maximum) {
      throw ServiceRequestException::invalid($field . ' is required and must not exceed ' . $maximum . ' characters.');
    }

    return $value;
  }

  private static function optionalText(?string $value, int $maximum, string $field): ?string
  {
    if (null === $value || '' === trim($value)) {
      return null;
    }

    return self::requiredText($value, $maximum, $field);
  }

  /**
   * @param array<array-key,mixed> $snapshot retained JSON identity
   *
   * @return array<string,mixed> isolated JSON values without mutable PHP references
   */
  private static function snapshot(array $snapshot): array
  {
    foreach ($snapshot as $key => $value) {
      if (!is_string($key)) {
        throw ServiceRequestException::invalid('Target snapshot must be a JSON object.');
      }
      self::snapshotValue($value, 1);
    }

    try {
      $json = json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
      if (strlen($json) > 65536) {
        throw ServiceRequestException::invalid('Target snapshot is too large.');
      }

      /** @var array<string,mixed> $isolated */
      $isolated = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

      return $isolated;
    } catch (JsonException) {
      throw ServiceRequestException::invalid('Target snapshot must contain valid JSON values.');
    }
  }

  private static function snapshotValue(mixed $value, int $depth): void
  {
    if ($depth > 16) {
      throw ServiceRequestException::invalid('Target snapshot is too deeply nested.');
    }
    if (is_array($value)) {
      foreach ($value as $nested) {
        self::snapshotValue($nested, $depth + 1);
      }
    } elseif (null !== $value && !is_string($value) && !is_int($value) && !is_float($value) && !is_bool($value)) {
      throw ServiceRequestException::invalid('Target snapshot must contain immutable JSON values.');
    }
  }
}
