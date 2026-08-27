<?php

declare(strict_types=1);

namespace Inspection\Application\Contract\Response;

use DateTimeImmutable;
use Inspection\Domain\Model\Response\InspectionResponse;

/**
 * Contract InspectionResponseView.
 *
 * The flat read model every inspection-response use case returns, and the
 * only thing `InspectionResponseProcessor` needs in order to build its
 * `InspectionResponseOutput` — the processor never sees the aggregate and
 * never sees a Doctrine record.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InspectionResponseView
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $id the response identifier
   * @param string $organizationId the owning organization identifier
   * @param ?string $interventionId the intervention identifier when intervention-scoped
   * @param string $inspectionId the inspection identifier
   * @param ?string $clientId the offline client identifier
   * @param string $recordStatus the representation lifecycle status
   * @param int $revision the optimistic-concurrency revision
   * @param string $itemKey the checklist item key
   * @param mixed $value the answer payload
   * @param DateTimeImmutable $createdAt the creation timestamp
   * @param DateTimeImmutable $updatedAt the last mutation timestamp
   */
  public function __construct(
    public string $id,
    public string $organizationId,
    public ?string $interventionId,
    public string $inspectionId,
    public ?string $clientId,
    public string $recordStatus,
    public int $revision,
    public string $itemKey,
    public mixed $value,
    public DateTimeImmutable $createdAt,
    public DateTimeImmutable $updatedAt,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method fromModel.
   *
   * Flattens the aggregate. Kept on the view rather than repeated in the
   * three handlers and the query that all return one — the mapping is
   * field-for-field and drifting copies of it is how an endpoint starts
   * answering a stale shape.
   *
   * @since 1.0.0
   *
   * @param InspectionResponse $response the response aggregate
   *
   * @return self the flattened view
   */
  public static function fromModel(InspectionResponse $response): self
  {
    return new self(
      id: (string) $response->id(),
      organizationId: (string) $response->organizationId(),
      interventionId: $response->interventionId(),
      inspectionId: (string) $response->inspectionId(),
      clientId: $response->clientId(),
      recordStatus: $response->status()->value,
      revision: $response->revision(),
      itemKey: $response->itemKey(),
      value: $response->value(),
      createdAt: $response->createdAt(),
      updatedAt: $response->updatedAt(),
    );
  }
  // #endregion
}
