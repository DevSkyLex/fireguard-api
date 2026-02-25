<?php

declare(strict_types=1);

namespace Onboarding\Domain\Model\OrganizationOnboardingSession;

use function is_string;

/**
 * Value Object StepHistoryEntry.
 *
 * Immutable record of a step completion or skip event within an onboarding
 * session. The {@see $completedAt} timestamp uses ISO 8601 (RFC 3339) format
 * so it can be stored as a plain JSON string without DateTimeImmutable mapping.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class StepHistoryEntry
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $stepKey the step key (see {@see \Onboarding\Domain\ValueObject\OrganizationOnboardingStep})
   * @param string $occurredAt ISO 8601 timestamp of when the event occurred
   * @param bool $skipped whether the step was skipped rather than executed
   */
  public function __construct(
    public string $stepKey,
    public string $occurredAt,
    public bool $skipped = false,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method toArray.
   *
   * @since 1.0.0
   *
   * @return array{stepKey: string, occurredAt: string, skipped: bool}
   */
  public function toArray(): array
  {
    return [
      'stepKey' => $this->stepKey,
      'occurredAt' => $this->occurredAt,
      'skipped' => $this->skipped,
    ];
  }

  /**
   * Method fromArray.
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $data
   */
  public static function fromArray(array $data): self
  {
    $stepKey = $data['stepKey'] ?? null;
    $occurredAt = $data['occurredAt'] ?? null;

    return new self(
      stepKey: is_string($stepKey) ? $stepKey : '',
      occurredAt: is_string($occurredAt) ? $occurredAt : '',
      skipped: (bool) ($data['skipped'] ?? false),
    );
  }
  // #endregion
}
