<?php

declare(strict_types=1);

namespace Maintenance\Domain\ValueObject;

use DateTimeImmutable;
use Shared\Domain\Exception\InvalidValueException;
use Shared\Domain\ValueObject\Uuid;

/**
 * Class MaintenanceOccurrenceAttempt
 *
 * Keeps generated work, attempt count and its validated receipt in one lifecycle.
 * A failed maintenance receipt is retained while completion remains absent.
 *
 * @category ValueObject
 */
final readonly class MaintenanceOccurrenceAttempt
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Requires linked work for an attempt and a validated receipt for completion.
   * The occurrence separately checks completion against its reservation instant.
   *
   * @access public
   *
   * @param int $number the explicit attempt count, zero before work is generated
   * @param ?string $interventionId the latest attempted intervention UUID
   * @param ?DateTimeImmutable $completedAt the successful completion instant
   * @param ?string $resultId the current attempt's validated result UUID
   *
   * @return void
   */
  public function __construct(
    public int $number,
    public ?string $interventionId,
    public ?DateTimeImmutable $completedAt,
    public ?string $resultId,
  ) {
    if (null !== $interventionId) {
      Uuid::assertValid($interventionId);
    }
    if (null !== $resultId) {
      Uuid::assertValid($resultId);
    }

    if ($number < 0 || (0 === $number) !== (null === $interventionId)) {
      throw InvalidValueException::because('An occurrence attempt must identify its intervention.');
    }

    if (null !== $resultId && null === $interventionId) {
      throw InvalidValueException::because('An occurrence result must belong to an explicit attempt.');
    }

    if (null !== $completedAt && null === $resultId) {
      throw InvalidValueException::because('A completed occurrence must retain a validated result after reservation.');
    }
  }
  // #endregion

  // #region Methods
  /**
   * Method unattempted
   *
   * Reserves an empty lifecycle without implying that work has been performed.
   *
   * @access public
   *
   * @return self the lifecycle before generated work
   */
  public static function unattempted(): self
  {
    return new self(0, null, null, null);
  }

  /**
   * Method beginAttempt
   *
   * Links the first work item and permits replay of its existing identity.
   *
   * @access public
   *
   * @param string $interventionId the generated intervention UUID
   *
   * @return self the started attempt or unchanged replay
   */
  public function beginAttempt(string $interventionId): self
  {
    Uuid::assertValid($interventionId);

    if ($this->interventionId === $interventionId) {
      return $this;
    }
    if (null !== $this->completedAt || null !== $this->interventionId) {
      throw InvalidValueException::because('An existing occurrence attempt needs an explicit retry.');
    }

    return new self(1, $interventionId, null, null);
  }

  /**
   * Method retryAttempt
   *
   * Replaces work only for an attempted open lifecycle, clearing its previous receipt.
   *
   * @access public
   *
   * @param string $interventionId the new intervention UUID
   *
   * @return self the next attempt or unchanged replay
   */
  public function retryAttempt(string $interventionId): self
  {
    Uuid::assertValid($interventionId);

    if (null !== $this->completedAt || null === $this->interventionId) {
      throw InvalidValueException::because('Only an attempted open occurrence can be retried.');
    }
    if ($this->interventionId === $interventionId) {
      return $this;
    }

    return new self($this->number + 1, $interventionId, null, null);
  }

  /**
   * Method validateResult
   *
   * Retains one receipt per attempt; unsuccessful servicing stays open and
   * control completion follows its operation kind even when defects remain.
   *
   * @access public
   *
   * @param MaintenanceOperationKind $kind the owning plan's operation kind
   * @param bool $successful whether the performed work succeeded
   * @param string $resultId the validated result UUID
   * @param DateTimeImmutable $validatedAt the validation instant checked against reservation by the occurrence
   *
   * @return self the validated lifecycle or unchanged replay
   */
  public function validateResult(MaintenanceOperationKind $kind, bool $successful, string $resultId, DateTimeImmutable $validatedAt): self
  {
    Uuid::assertValid($resultId);

    if ($this->resultId === $resultId) {
      return $this;
    }
    if (null === $this->interventionId || null !== $this->resultId || null !== $this->completedAt) {
      throw InvalidValueException::because('A result must validate the current occurrence attempt exactly once.');
    }

    return new self($this->number, $this->interventionId, $kind->completesOccurrence($successful) ? $validatedAt : null, $resultId);
  }
  // #endregion
}
