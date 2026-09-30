<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Query\NonConformity\GetNonConformity;

use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

/**
 * Class GetNonConformityResult
 *
 * Carries the non-conformity details and timestamps for an inspection query.
 *
 * @category Result
 */
final readonly class GetNonConformityResult implements ResultMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Carries the finding, its lifecycle timestamps, and the related inspection identifier for API output.
   *
   * @access public
   *
   * @param string $nonConformityId the non-conformity identifier
   * @param string $inspectionId the owning inspection identifier
   * @param string $description the recorded deficiency description
   * @param string $severity the severity value
   * @param string $status the non-conformity status value
   * @param string|null $dueAt the optional due timestamp
   * @param string|null $resolvedAt the optional resolution timestamp
   * @param string|null $notes the optional resolution notes
   * @param DateTimeImmutable $createdAt the creation timestamp
   * @param DateTimeImmutable $updatedAt the last update timestamp
   *
   * @return void
   */
  public function __construct(
    public string $nonConformityId,
    public string $inspectionId,
    public string $description,
    public string $severity,
    public string $status,
    public ?string $dueAt,
    public ?string $resolvedAt,
    public ?string $notes,
    public DateTimeImmutable $createdAt,
    public DateTimeImmutable $updatedAt,
  ) {
  }
  // #endregion
}
