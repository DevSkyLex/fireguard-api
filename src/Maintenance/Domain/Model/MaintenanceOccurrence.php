<?php

declare(strict_types=1);

namespace Maintenance\Domain\Model;

use DateTimeImmutable;
use Maintenance\Domain\ValueObject\MaintenanceOperationKind;
use Shared\Domain\Exception\InvalidValueException;
use Shared\Domain\ValueObject\Uuid;

/**
 * Class MaintenanceOccurrence
 *
 * Retains an immutable due date and permits an explicit retry of the same work.
 *
 * @category Model
 */
final class MaintenanceOccurrence
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Restores validated state independently of campaign creation.
   *
   * @access private
   *
   * @param string $id the occurrence UUID
   * @param string $planId the owning plan UUID
   * @param DateTimeImmutable $dueAt the immutable original due date
   * @param DateTimeImmutable $createdAt the reservation instant
   * @param int $attempt the number of explicitly started attempts
   * @param ?string $interventionId the latest attempted intervention UUID
   * @param ?DateTimeImmutable $completedAt the validated completion instant
   * @param ?string $resultId the latest validated result UUID
   *
   * @return void
   */
  private function __construct(
    public readonly string $id,
    public readonly string $planId,
    public readonly DateTimeImmutable $dueAt,
    public readonly DateTimeImmutable $createdAt,
    private int $attempt,
    private ?string $interventionId,
    private ?DateTimeImmutable $completedAt,
    private ?string $resultId,
  ) {
    new Uuid($id);
    new Uuid($planId);
    if (null !== $interventionId) {
      new Uuid($interventionId);
    }
    if (null !== $resultId) {
      new Uuid($resultId);
    }

    if ($attempt < 0 || (0 === $attempt) !== (null === $interventionId)) {
      throw InvalidValueException::because('An occurrence attempt must identify its intervention.');
    }

    if (null !== $resultId && null === $interventionId) {
      throw InvalidValueException::because('An occurrence result must belong to an explicit attempt.');
    }

    if (null !== $completedAt && (null === $resultId || $completedAt < $createdAt)) {
      throw InvalidValueException::because('A completed occurrence must retain a validated result after reservation.');
    }
  }
  // #endregion

  // #region Methods
  /**
   * Method open
   *
   * Reserves an occurrence without claiming that any work has been performed.
   *
   * @access public
   *
   * @param string $id the occurrence UUID
   * @param string $planId the owning plan UUID
   * @param DateTimeImmutable $dueAt the original due date
   * @param DateTimeImmutable $now the reservation instant
   *
   * @return self the open occurrence
   */
  public static function open(string $id, string $planId, DateTimeImmutable $dueAt, DateTimeImmutable $now): self
  {
    return new self($id, $planId, $dueAt, $now, 0, null, null, null);
  }

  /**
   * Method reconstitute
   *
   * Restores an occurrence without losing its due date or explicit attempt.
   *
   * @access public
   *
   * @param string $id the occurrence UUID
   * @param string $planId the owning plan UUID
   * @param DateTimeImmutable $dueAt the immutable original due date
   * @param DateTimeImmutable $createdAt the reservation instant
   * @param int $attempt the explicit attempt count
   * @param ?string $interventionId the latest attempted intervention UUID
   * @param ?DateTimeImmutable $completedAt the completion instant
   * @param ?string $resultId the latest validated result UUID
   *
   * @return self the restored occurrence
   */
  public static function reconstitute(
    string $id,
    string $planId,
    DateTimeImmutable $dueAt,
    DateTimeImmutable $createdAt,
    int $attempt,
    ?string $interventionId,
    ?DateTimeImmutable $completedAt,
    ?string $resultId,
  ): self {
    return new self($id, $planId, $dueAt, $createdAt, $attempt, $interventionId, $completedAt, $resultId);
  }

  /**
   * Method beginAttempt
   *
   * Links generated work without completing the occurrence; replay is harmless.
   *
   * @access public
   *
   * @param string $interventionId the generated intervention UUID
   *
   * @return void
   */
  public function beginAttempt(string $interventionId): void
  {
    new Uuid($interventionId);

    if ($this->interventionId === $interventionId) {
      return;
    }
    if (null !== $this->completedAt || null !== $this->interventionId) {
      throw InvalidValueException::because('An existing occurrence attempt needs an explicit retry.');
    }

    $this->interventionId = $interventionId;
    $this->attempt = 1;
  }

  /**
   * Method retryAttempt
   *
   * Starts another explicit attempt after the caller verifies the old work is
   * cancelled or unsuccessful. The original occurrence and due date survive.
   *
   * @access public
   *
   * @param string $interventionId the new intervention UUID
   *
   * @return void
   */
  public function retryAttempt(string $interventionId): void
  {
    new Uuid($interventionId);

    if (null !== $this->completedAt || null === $this->interventionId) {
      throw InvalidValueException::because('Only an attempted open occurrence can be retried.');
    }
    if ($this->interventionId === $interventionId) {
      return;
    }

    ++$this->attempt;
    $this->interventionId = $interventionId;
    $this->resultId = null;
  }

  /**
   * Method validateResult
   *
   * Completes a validated control regardless of defects; failed maintenance
   * stays open. The same result can be replayed without changing completion.
   *
   * @access public
   *
   * @param MaintenanceOperationKind $kind the owning plan's operation kind
   * @param bool $successful whether the executed work succeeded
   * @param string $resultId the validated result UUID
   * @param DateTimeImmutable $validatedAt the validation instant
   *
   * @return bool whether the occurrence is completed
   */
  public function validateResult(
    MaintenanceOperationKind $kind,
    bool $successful,
    string $resultId,
    DateTimeImmutable $validatedAt,
  ): bool {
    new Uuid($resultId);

    if ($this->resultId === $resultId) {
      return null !== $this->completedAt;
    }
    if (null === $this->interventionId || null !== $this->resultId || null !== $this->completedAt || $validatedAt < $this->createdAt) {
      throw InvalidValueException::because('A result must validate the current occurrence attempt exactly once.');
    }

    $this->resultId = $resultId;
    if ($kind->completesOccurrence($successful)) {
      $this->completedAt = $validatedAt;
    }

    return null !== $this->completedAt;
  }

  /**
   * Method state
   *
   * Exposes the persisted occurrence lifecycle.
   *
   * @access public
   *
   * @return string open or completed
   */
  public function state(): string
  {
    return null === $this->completedAt ? 'open' : 'completed';
  }

  /**
   * Method attempt
   *
   * Returns the explicit attempt number, zero before work is generated.
   *
   * @access public
   *
   * @return int the attempt number
   */
  public function attempt(): int
  {
    return $this->attempt;
  }

  /**
   * Method interventionId
   *
   * Returns the latest work identifier without implying completion.
   *
   * @access public
   *
   * @return ?string the intervention UUID
   */
  public function interventionId(): ?string
  {
    return $this->interventionId;
  }

  /**
   * Method completedAt
   *
   * Returns the validated completion instant.
   *
   * @access public
   *
   * @return ?DateTimeImmutable null while the occurrence remains open
   */
  public function completedAt(): ?DateTimeImmutable
  {
    return $this->completedAt;
  }

  /**
   * Method resultId
   *
   * Identifies the current attempt's validated result.
   *
   * @access public
   *
   * @return ?string the result UUID
   */
  public function resultId(): ?string
  {
    return $this->resultId;
  }
  // #endregion
}
