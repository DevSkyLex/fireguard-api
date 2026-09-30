<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Command\NonConformity\AddNonConformity;

use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase AddNonConformityResult.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AddNonConformityResult implements ResultMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Returns the created non-conformity details and its lifecycle timestamps.
   *
   * @access public
   *
   * @param string $nonConformityId identifier assigned to the finding
   * @param string $inspectionId inspection containing the finding
   * @param string $description recorded deficiency description
   * @param string $severity recorded severity
   * @param string $status initial lifecycle status
   * @param ?string $dueAt resolution deadline, when set
   * @param ?string $notes handling notes, when set
   * @param DateTimeImmutable $createdAt time the finding was created
   * @param DateTimeImmutable $updatedAt time the finding was last updated
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
    public ?string $notes,
    public DateTimeImmutable $createdAt,
    public DateTimeImmutable $updatedAt,
  ) {
  }
  // #endregion
}
