<?php

declare(strict_types=1);

namespace Inspection\Domain\Model\Response;

use DateTimeImmutable;
use Inspection\Domain\Exception\{InspectionResponseConflictException, InspectionRevisionMismatchException};
use Inspection\Domain\ValueObject\{InspectionId, InspectionOrganizationId, InspectionResponseId, InspectionResponseStatus};

/**
 * Model InspectionResponse.
 *
 * One answer to one checklist item of one inspection — the row an offline
 * field client creates, edits and replays while an intervention is being
 * prepared, and the immutable trace it leaves once that intervention
 * publishes.
 *
 * Two invariants live here rather than in the processor that used to hold
 * them: a `PUBLISHED` response can be neither edited nor deleted, and every
 * edit bumps `revision`, which is the `If-Match` token the API exposes.
 *
 * `interventionId` stays a raw string. It identifies a row in another
 * module, this module never validates it, and narrowing it to a UUID value
 * object would make reconstituting an already-persisted row able to fail —
 * a read that cannot fail today.
 *
 * @category Model
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InspectionResponse
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param InspectionResponseId $id the response identifier
   * @param InspectionOrganizationId $organizationId the owning organization identifier
   * @param InspectionId $inspectionId the inspection identifier
   * @param ?string $interventionId the intervention identifier when the response is intervention-scoped
   * @param ?string $clientId the offline client identifier
   * @param InspectionResponseStatus $status the representation lifecycle status
   * @param int $revision the optimistic-concurrency revision
   * @param string $itemKey the checklist item key
   * @param mixed $value the answer payload
   * @param DateTimeImmutable $createdAt the creation timestamp
   * @param DateTimeImmutable $updatedAt the last mutation timestamp
   */
  private function __construct(
    private InspectionResponseId $id,
    private InspectionOrganizationId $organizationId,
    private InspectionId $inspectionId,
    private ?string $interventionId,
    private ?string $clientId,
    private InspectionResponseStatus $status,
    private int $revision,
    private string $itemKey,
    private mixed $value,
    private DateTimeImmutable $createdAt,
    private DateTimeImmutable $updatedAt,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method create.
   *
   * Creates a new inspection response.
   *
   * A response tied to an intervention starts as a `DRAFT` representation —
   * it is part of a preparation the field client may still change. One
   * created outside any intervention is `PUBLISHED` on arrival, because
   * nothing will later publish it.
   *
   * @since 1.0.0
   *
   * @param InspectionResponseId $id the response identifier
   * @param InspectionOrganizationId $organizationId the owning organization identifier
   * @param InspectionId $inspectionId the inspection identifier
   * @param string $itemKey the checklist item key
   * @param mixed $value the answer payload
   * @param ?string $interventionId the intervention identifier when intervention-scoped
   * @param ?string $clientId the offline client identifier
   *
   * @return self the created response
   */
  public static function create(
    InspectionResponseId $id,
    InspectionOrganizationId $organizationId,
    InspectionId $inspectionId,
    string $itemKey,
    mixed $value,
    ?string $interventionId = null,
    ?string $clientId = null,
  ): self {
    $now = new DateTimeImmutable();

    return new self(
      id: $id,
      organizationId: $organizationId,
      inspectionId: $inspectionId,
      interventionId: $interventionId,
      clientId: $clientId,
      status: null === $interventionId ? InspectionResponseStatus::PUBLISHED : InspectionResponseStatus::DRAFT,
      revision: 1,
      itemKey: $itemKey,
      value: $value,
      createdAt: $now,
      updatedAt: $now,
    );
  }

  /**
   * Method reconstitute.
   *
   * Reconstitutes a response from persisted state.
   *
   * @since 1.0.0
   *
   * @param InspectionResponseId $id the response identifier
   * @param InspectionOrganizationId $organizationId the owning organization identifier
   * @param InspectionId $inspectionId the inspection identifier
   * @param ?string $interventionId the intervention identifier
   * @param ?string $clientId the offline client identifier
   * @param InspectionResponseStatus $status the representation lifecycle status
   * @param int $revision the optimistic-concurrency revision
   * @param string $itemKey the checklist item key
   * @param mixed $value the answer payload
   * @param DateTimeImmutable $createdAt the creation timestamp
   * @param DateTimeImmutable $updatedAt the last mutation timestamp
   *
   * @return self the reconstituted response
   */
  public static function reconstitute(
    InspectionResponseId $id,
    InspectionOrganizationId $organizationId,
    InspectionId $inspectionId,
    ?string $interventionId,
    ?string $clientId,
    InspectionResponseStatus $status,
    int $revision,
    string $itemKey,
    mixed $value,
    DateTimeImmutable $createdAt,
    DateTimeImmutable $updatedAt,
  ): self {
    return new self(
      id: $id,
      organizationId: $organizationId,
      inspectionId: $inspectionId,
      interventionId: $interventionId,
      clientId: $clientId,
      status: $status,
      revision: $revision,
      itemKey: $itemKey,
      value: $value,
      createdAt: $createdAt,
      updatedAt: $updatedAt,
    );
  }

  /**
   * Method updateValue.
   *
   * Replaces the answer payload and bumps the revision.
   *
   * @since 1.0.0
   *
   * @param mixed $value the new answer payload
   *
   * @throws InspectionResponseConflictException when the response is already published
   */
  public function updateValue(mixed $value): void
  {
    if (InspectionResponseStatus::DRAFT !== $this->status) {
      throw InspectionResponseConflictException::publishedIsImmutable();
    }

    $this->value = $value;
    ++$this->revision;
    $this->updatedAt = new DateTimeImmutable();
  }

  /**
   * Method assertDeletable.
   *
   * Rejects the deletion of a published response.
   *
   * @since 1.0.0
   *
   * @throws InspectionResponseConflictException when the response is already published
   */
  public function assertDeletable(): void
  {
    if (InspectionResponseStatus::DRAFT !== $this->status) {
      throw InspectionResponseConflictException::publishedCannotBeDeleted();
    }
  }

  /**
   * Method assertRevisionMatches.
   *
   * Re-runs the optimistic-concurrency check inside the mutating transaction.
   *
   * @since 1.0.0
   *
   * @param int $expectedRevision the revision the caller declared through `If-Match`
   *
   * @throws InspectionRevisionMismatchException when the stored revision moved on
   */
  public function assertRevisionMatches(int $expectedRevision): void
  {
    if ($this->revision !== $expectedRevision) {
      throw InspectionRevisionMismatchException::stale();
    }
  }

  /**
   * Method id.
   *
   * @since 1.0.0
   */
  public function id(): InspectionResponseId
  {
    return $this->id;
  }

  /**
   * Method organizationId.
   *
   * @since 1.0.0
   */
  public function organizationId(): InspectionOrganizationId
  {
    return $this->organizationId;
  }

  /**
   * Method inspectionId.
   *
   * @since 1.0.0
   */
  public function inspectionId(): InspectionId
  {
    return $this->inspectionId;
  }

  /**
   * Method interventionId.
   *
   * @since 1.0.0
   */
  public function interventionId(): ?string
  {
    return $this->interventionId;
  }

  /**
   * Method clientId.
   *
   * @since 1.0.0
   */
  public function clientId(): ?string
  {
    return $this->clientId;
  }

  /**
   * Method status.
   *
   * @since 1.0.0
   */
  public function status(): InspectionResponseStatus
  {
    return $this->status;
  }

  /**
   * Method revision.
   *
   * @since 1.0.0
   */
  public function revision(): int
  {
    return $this->revision;
  }

  /**
   * Method itemKey.
   *
   * @since 1.0.0
   */
  public function itemKey(): string
  {
    return $this->itemKey;
  }

  /**
   * Method value.
   *
   * @since 1.0.0
   */
  public function value(): mixed
  {
    return $this->value;
  }

  /**
   * Method createdAt.
   *
   * @since 1.0.0
   */
  public function createdAt(): DateTimeImmutable
  {
    return $this->createdAt;
  }

  /**
   * Method updatedAt.
   *
   * @since 1.0.0
   */
  public function updatedAt(): DateTimeImmutable
  {
    return $this->updatedAt;
  }
  // #endregion
}
