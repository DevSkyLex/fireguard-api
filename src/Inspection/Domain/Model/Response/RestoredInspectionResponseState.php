<?php

declare(strict_types=1);

namespace Inspection\Domain\Model\Response;

use Inspection\Domain\ValueObject\InspectionResponseStatus;

/** Persisted answer and offline-sync lifecycle state. */
final readonly class RestoredInspectionResponseState
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Restores the submitted answer and synchronization state for one inspection item.
   *
   * @access public
   *
   * @param ?string $interventionId optional intervention context used for the response
   * @param ?string $clientId optional client identifier for offline synchronization
   * @param InspectionResponseStatus $status current lifecycle state of the response
   * @param int $revision persisted response revision
   * @param string $itemKey stable key of the checklist item being answered
   * @param mixed $value stored answer value, whose shape depends on the checklist item
   *
   * @return void
   */
  public function __construct(
    public ?string $interventionId,
    public ?string $clientId,
    public InspectionResponseStatus $status,
    public int $revision,
    public string $itemKey,
    public mixed $value,
  ) {
  }
  // #endregion
}
