<?php

declare(strict_types=1);

namespace Inspection\Application\Contract\Inspection;

/**
 * Filters shared by inspection list, count and bounded CSV export queries.
 */
final readonly class InspectionListCriteria
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Combines subject, execution, inspector, and text filters used by inspection list and count queries.
   *
   * @access public
   *
   * @param InspectionSubjectCriteria $subject equipment, facility, and checklist filters
   * @param InspectionExecutionCriteria $execution result, status, and performed-time filters
   * @param InspectionInspectorCriteria $inspector inspector identity and type filters
   * @param ?string $search optional free-text query applied to inspection fields
   *
   * @return void
   */
  public function __construct(
    public InspectionSubjectCriteria $subject = new InspectionSubjectCriteria(),
    public InspectionExecutionCriteria $execution = new InspectionExecutionCriteria(),
    public InspectionInspectorCriteria $inspector = new InspectionInspectorCriteria(),
    public ?string $search = null,
  ) {
  }
  // #endregion

  /**
   * CSV export deliberately omits inspector type and free-text search.
   */
  public function forExport(): self
  {
    return new self(
      subject: $this->subject,
      execution: $this->execution,
      inspector: new InspectionInspectorCriteria(userId: $this->inspector->userId),
    );
  }
}
