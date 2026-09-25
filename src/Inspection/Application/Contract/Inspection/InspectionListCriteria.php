<?php

declare(strict_types=1);

namespace Inspection\Application\Contract\Inspection;

/**
 * Filters shared by inspection list, count and bounded CSV export queries.
 */
final readonly class InspectionListCriteria
{
  public function __construct(
    public InspectionSubjectCriteria $subject = new InspectionSubjectCriteria(),
    public InspectionExecutionCriteria $execution = new InspectionExecutionCriteria(),
    public InspectionInspectorCriteria $inspector = new InspectionInspectorCriteria(),
    public ?string $search = null,
  ) {
  }

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
