<?php

declare(strict_types=1);

namespace Intervention\Domain\ValueObject;

use DateMalformedStringException;
use DateTimeImmutable;
use Intervention\Domain\Exception\InterventionValidationException;

use function in_array;
use function is_string;
use function mb_strlen;
use function preg_match;
use function trim;

/**
 * Class WorkItemExecutionResult
 *
 * Records the actual equipment operation independently of its scheduled date.
 *
 * @category ValueObject
 */
final readonly class WorkItemExecutionResult
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Validates the operation fact before it can be staged for review.
   *
   * @access private
   *
   * @param string $equipmentId equipment that received the operation
   * @param DateTimeImmutable $performedAt actual execution instant
   * @param string $outcome successful, failed or performed
   * @param string $workPerformed description of the executed work
   *
   * @return void
   */
  private function __construct(
    public string $equipmentId,
    public DateTimeImmutable $performedAt,
    public string $outcome,
    public string $workPerformed,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method fromPayload
   *
   * Requires an actual date with timezone and an explicit result; author and validation are server-owned.
   *
   * @access public
   *
   * @param array<string, mixed> $payload operation data supplied by an executor
   *
   * @return self the validated operation fact
   */
  public static function fromPayload(array $payload): self
  {
    $equipmentId = $payload['equipmentId'] ?? null;
    $performedAt = $payload['performedAt'] ?? null;
    $outcome = $payload['outcome'] ?? null;
    $workPerformed = $payload['workPerformed'] ?? null;
    if (!is_string($equipmentId) || 1 !== preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $equipmentId)) {
      throw new InterventionValidationException('The result must identify an equipment UUID.');
    }
    if (!is_string($performedAt) || 1 !== preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-]\d{2}:\d{2})$/', $performedAt)) {
      throw new InterventionValidationException('The actual execution date must include its timezone.');
    }

    try {
      $date = new DateTimeImmutable($performedAt);
    } catch (DateMalformedStringException) {
      throw new InterventionValidationException('The actual execution date is invalid.');
    }
    $errors = DateTimeImmutable::getLastErrors();
    if (false !== $errors && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
      throw new InterventionValidationException('The actual execution date is invalid.');
    }
    if (!is_string($outcome) || !in_array($outcome, ['successful', 'failed', 'performed'], true)) {
      throw new InterventionValidationException('The operation outcome is invalid.');
    }
    if (!is_string($workPerformed) || '' === trim($workPerformed) || mb_strlen($workPerformed) > 10000) {
      throw new InterventionValidationException('Describe the executed work in at most 10000 characters.');
    }

    return new self($equipmentId, $date, $outcome, trim($workPerformed));
  }

  /**
   * Method toArray
   *
   * Returns the factual payload without accepting caller-owned attribution or review state.
   *
   * @access public
   *
   * @return array{equipmentId:string, performedAt:string, outcome:string, workPerformed:string}
   */
  public function toArray(): array
  {
    return ['equipmentId' => $this->equipmentId, 'performedAt' => $this->performedAt->format('c'), 'outcome' => $this->outcome, 'workPerformed' => $this->workPerformed];
  }

  /**
   * Method assertCompletesAction
   *
   * A failed fact is retained but never completes repair, replacement or maintenance work.
   *
   * @access public
   *
   * @param string $action equipment work action
   * @param string $targetEquipmentId prepared equipment identity
   *
   * @return void
   */
  public function assertCompletesAction(string $action, string $targetEquipmentId): void
  {
    if ($this->equipmentId !== $targetEquipmentId || !in_array($action, ['maintenance', 'repair', 'replacement'], true)
      || 'failed' === $this->outcome || ('maintenance' !== $action && 'successful' !== $this->outcome)) {
      throw new InterventionValidationException('Completing this operation requires a successful result for its prepared equipment.');
    }
  }

  /**
   * Method assertAlreadyPerformed
   *
   * Distinguishes an actual execution fact from a scheduled future operation.
   *
   * @access public
   *
   * @param DateTimeImmutable $recordedAt server instant when the fact is being recorded or validated
   *
   * @return void
   */
  public function assertAlreadyPerformed(DateTimeImmutable $recordedAt): void
  {
    if ($this->performedAt > $recordedAt) {
      throw new InterventionValidationException('The actual execution date cannot be in the future.');
    }
  }
  // #endregion
}
