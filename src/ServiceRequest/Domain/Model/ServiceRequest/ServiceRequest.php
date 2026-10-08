<?php

declare(strict_types=1);

namespace ServiceRequest\Domain\Model\ServiceRequest;

use DateTimeImmutable;
use JsonException;
use ServiceRequest\Domain\Exception\ServiceRequestException;
use ServiceRequest\Domain\ValueObject\{ServiceRequestContent, ServiceRequestLifecycle, ServiceRequestTarget, ServiceRequestTimeline};
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
   * Constant INVALID_TARGET_SNAPSHOT_MESSAGE
   */
  private const string INVALID_TARGET_SNAPSHOT_MESSAGE = 'Target snapshot must be a JSON object.';

  // #region Properties
  /**
   * Property id. Stable request identity.
   */
  public string $id;

  /**
   * Property organizationId. Owning organization.
   */
  public string $organizationId;

  /**
   * Property equipmentId. Selected equipment, absent for an unqualified site-only request.
   */
  public ?string $equipmentId;

  /**
   * Property siteId. Original root site or explicit reserve-equipment absence.
   */
  public ?string $siteId;

  /**
   * Property targetSnapshot
   *
   * @var array<string,mixed> isolated retained owner identity
   */
  public array $targetSnapshot;

  /**
   * Property title. Retained request title.
   */
  public string $title;

  /**
   * Property description. Retained repair explanation.
   */
  public string $description;

  /**
   * Property priority. Retained priority.
   */
  public string $priority;

  /**
   * Property originInspectionId. Original inspection evidence.
   */
  public ?string $originInspectionId;

  /**
   * Property originNonConformityId. Original non-conformity evidence.
   */
  public ?string $originNonConformityId;

  /**
   * Property status. Retained lifecycle state.
   */
  public string $status;

  /**
   * Property revision. Exact optimistic revision.
   */
  public int $revision;

  /**
   * Property requestedAt. Original creation instant.
   */
  public DateTimeImmutable $requestedAt;

  /**
   * Property updatedAt. Latest change instant.
   */
  public DateTimeImmutable $updatedAt;

  /**
   * Property qualifiedAt. Original qualification instant.
   */
  public ?DateTimeImmutable $qualifiedAt;

  /**
   * Property rejectedAt. Explicit rejection instant.
   */
  public ?DateTimeImmutable $rejectedAt;

  /**
   * Property cancelledAt. Explicit cancellation instant.
   */
  public ?DateTimeImmutable $cancelledAt;

  /**
   * Property convertedAt. Committed conversion instant.
   */
  public ?DateTimeImmutable $convertedAt;

  /**
   * Property decisionReason. Retained rejection or cancellation reason.
   */
  public ?string $decisionReason;

  /**
   * Property qualificationNote. Retained qualification explanation.
   */
  public ?string $qualificationNote;

  /**
   * Property interventionId. Committed conversion intervention.
   */
  public ?string $interventionId;

  /**
   * Property taskId. Committed conversion task.
   */
  public ?string $taskId;
  // #endregion

  // #region Constructor
  /**
   * Method __construct
   *
   * Every public field preserves its persisted meaning while typed state groups keep restoration explicit.
   *
   * @access private
   *
   * @param string $id retained request identity
   * @param string $organizationId owning organization
   * @param ServiceRequestTarget $target isolated retained target and origin evidence
   * @param ServiceRequestContent $content retained request content
   * @param ServiceRequestLifecycle $lifecycle retained revision, decisions and chronology
   *
   * @return void
   */
  private function __construct(string $id, string $organizationId, ServiceRequestTarget $target, ServiceRequestContent $content, ServiceRequestLifecycle $lifecycle)
  {
    $this->id = $id;
    $this->organizationId = $organizationId;
    $this->equipmentId = $target->equipmentId;
    $this->siteId = $target->siteId;
    $this->targetSnapshot = $target->snapshot;
    $this->originInspectionId = $target->originInspectionId;
    $this->originNonConformityId = $target->originNonConformityId;
    $this->title = $content->title;
    $this->description = $content->description;
    $this->priority = $content->priority;
    $this->status = $lifecycle->status;
    $this->revision = $lifecycle->revision;
    $this->requestedAt = $lifecycle->timeline->requestedAt;
    $this->updatedAt = $lifecycle->timeline->updatedAt;
    $this->qualifiedAt = $lifecycle->timeline->qualifiedAt;
    $this->rejectedAt = $lifecycle->timeline->rejectedAt;
    $this->cancelledAt = $lifecycle->timeline->cancelledAt;
    $this->convertedAt = $lifecycle->timeline->convertedAt;
    $this->decisionReason = $lifecycle->decisionReason;
    $this->qualificationNote = $lifecycle->qualificationNote;
    $this->interventionId = $lifecycle->interventionId;
    $this->taskId = $lifecycle->taskId;
  }
  // #endregion

  // #region Methods
  /**
   * Method create
   *
   * Validates newly declared references before isolating the target and normalizing content.
   *
   * @access public
   *
   * @param string $id generated request identity
   * @param string $organizationId owning organization
   * @param ServiceRequestTarget $target declared owner target and origin evidence
   * @param ServiceRequestContent $content declared title, description and priority
   * @param DateTimeImmutable $now creation instant
   *
   * @return self requested state at revision one
   */
  public static function create(string $id, string $organizationId, ServiceRequestTarget $target, ServiceRequestContent $content, DateTimeImmutable $now): self
  {
    self::uuid($id);
    self::uuid($organizationId);
    foreach ([$target->equipmentId, $target->siteId, $target->originInspectionId, $target->originNonConformityId] as $reference) {
      if (null !== $reference) {
        self::uuid($reference);
      }
    }
    if (null === $target->equipmentId && null === $target->siteId) {
      throw ServiceRequestException::invalid('A service request must target an equipment or a site.');
    }
    $target = new ServiceRequestTarget($target->equipmentId, $target->siteId, self::snapshot($target->snapshot), $target->originInspectionId, $target->originNonConformityId);
    $content = new ServiceRequestContent(self::requiredText($content->title, 160, 'Title'), self::requiredText($content->description, 10000, 'Description'), self::priority($content->priority));

    return new self($id, $organizationId, $target, $content, new ServiceRequestLifecycle('requested', 1, new ServiceRequestTimeline($now, $now)));
  }

  /**
   * Method reconstitute
   *
   * Historical content and lifecycle are retained exactly; only JSON isolation is reapplied.
   *
   * @access public
   *
   * @param string $id persisted request identity
   * @param string $organizationId persisted owning organization
   * @param ServiceRequestTarget $target persisted target and original evidence
   * @param ServiceRequestContent $content persisted content without new-write normalization
   * @param ServiceRequestLifecycle $lifecycle persisted revision, decisions and all lifecycle dates
   *
   * @return self exact persisted aggregate
   */
  public static function reconstitute(string $id, string $organizationId, ServiceRequestTarget $target, ServiceRequestContent $content, ServiceRequestLifecycle $lifecycle): self
  {
    $target = new ServiceRequestTarget($target->equipmentId, $target->siteId, self::snapshot($target->snapshot), $target->originInspectionId, $target->originNonConformityId);

    return new self($id, $organizationId, $target, $content, $lifecycle);
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

    return new self($this->id, $this->organizationId, $this->target(), new ServiceRequestContent($title, $description, $priority), new ServiceRequestLifecycle($this->status, $this->revision + 1, new ServiceRequestTimeline($this->requestedAt, $now, $this->qualifiedAt, $this->rejectedAt, $this->cancelledAt, $this->convertedAt), $this->decisionReason, $this->qualificationNote, $this->interventionId, $this->taskId));
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

    return new self($this->id, $this->organizationId, new ServiceRequestTarget($equipmentId, $siteId, self::snapshot($targetSnapshot), $this->originInspectionId, $this->originNonConformityId), new ServiceRequestContent($this->title, $this->description, $this->priority), new ServiceRequestLifecycle($this->status, $this->revision + 1, new ServiceRequestTimeline($this->requestedAt, $now, $this->qualifiedAt, $this->rejectedAt, $this->cancelledAt, $this->convertedAt), $this->decisionReason, $this->qualificationNote, $this->interventionId, $this->taskId));
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
    return new self($this->id, $this->organizationId, $this->target(), new ServiceRequestContent($this->title, $this->description, $this->priority), new ServiceRequestLifecycle($status, $this->revision + 1, new ServiceRequestTimeline($this->requestedAt, $now, 'qualified' === $status ? $now : $this->qualifiedAt, 'rejected' === $status ? $now : $this->rejectedAt, 'cancelled' === $status ? $now : $this->cancelledAt, 'converted' === $status ? $now : $this->convertedAt), $decisionReason ?? $this->decisionReason, $qualificationNote ?? $this->qualificationNote, $interventionId ?? $this->interventionId, $taskId ?? $this->taskId));
  }

  /**
   * Method target
   *
   * @access private
   *
   * @return ServiceRequestTarget retained target and original evidence
   */
  private function target(): ServiceRequestTarget
  {
    return new ServiceRequestTarget($this->equipmentId, $this->siteId, $this->targetSnapshot, $this->originInspectionId, $this->originNonConformityId);
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
      Uuid::assertValid($value);
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
        throw ServiceRequestException::invalid(self::INVALID_TARGET_SNAPSHOT_MESSAGE);
      }
      self::snapshotValue($value, 1);
    }

    try {
      $json = json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
      if (strlen($json) > 65536) {
        throw ServiceRequestException::invalid('Target snapshot is too large.');
      }

      return self::snapshotObject(json_decode($json, true, 512, JSON_THROW_ON_ERROR));
    } catch (JsonException) {
      throw ServiceRequestException::invalid('Target snapshot must contain valid JSON values.');
    }
  }

  /**
   * Method snapshotObject
   *
   * Restores the decoded object through a checked string-keyed mapping.
   *
   * @access private
   *
   * @param mixed $decoded isolated JSON result
   *
   * @return array<string,mixed> explicitly object-shaped isolated identity
   */
  private static function snapshotObject(mixed $decoded): array
  {
    if (!is_array($decoded)) {
      throw ServiceRequestException::invalid(self::INVALID_TARGET_SNAPSHOT_MESSAGE);
    }
    $snapshot = [];
    foreach ($decoded as $key => $value) {
      if (!is_string($key)) {
        throw ServiceRequestException::invalid(self::INVALID_TARGET_SNAPSHOT_MESSAGE);
      }
      $snapshot[$key] = $value;
    }

    return $snapshot;
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
  // #endregion
}
