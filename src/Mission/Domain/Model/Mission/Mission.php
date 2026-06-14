<?php

declare(strict_types=1);

namespace Mission\Domain\Model\Mission;

use DateTimeImmutable;
use Mission\Domain\Exception\MissionConflictException;
use Mission\Domain\Service\MissionTransitionPolicy;
use Mission\Domain\ValueObject\{MissionPriority, MissionStatus, MissionType};

use function array_unique;
use function array_values;
use function mb_strlen;
use function trim;

/**
 * Domain Mission.
 *
 * @category Domain
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class Mission
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $id the id value
   * @param string $organizationId the organization id value
   * @param MissionType $type the type value
   * @param string $name the name value
   * @param MissionStatus $status the status value
   * @param string $referencePackId the reference pack id value
   * @param ?string $siteId the site id value
   * @param ?string $responsibleId the responsible id value
   * @param list<string> $participants
   * @param MissionPriority $priority the priority value
   * @param ?DateTimeImmutable $plannedStartAt the planned start at value
   * @param ?DateTimeImmutable $dueAt the due at value
   * @param ?string $reviewNote the review note value
   * @param int $revision the revision value
   * @param DateTimeImmutable $createdAt the created at value
   * @param DateTimeImmutable $updatedAt the updated at value
   */
  private function __construct(
    private readonly string $id,
    private readonly string $organizationId,
    private MissionType $type,
    private string $name,
    private MissionStatus $status,
    private string $referencePackId,
    private ?string $siteId,
    private ?string $responsibleId,
    private array $participants,
    private MissionPriority $priority,
    private ?DateTimeImmutable $plannedStartAt,
    private ?DateTimeImmutable $dueAt,
    private ?string $reviewNote,
    private int $revision,
    private readonly DateTimeImmutable $createdAt,
    private DateTimeImmutable $updatedAt,
  ) {
  }

  /**
   * Method create.
   *
   * @since 1.0.0
   *
   * @param string $id the id value
   * @param string $organizationId the organization id value
   * @param MissionType $type the type value
   * @param string $name the name value
   * @param string $referencePackId the reference pack id value
   * @param ?string $siteId the site id value
   * @param ?string $responsibleId the responsible id value
   * @param list<string> $participants
   * @param MissionPriority $priority the priority value
   * @param ?DateTimeImmutable $plannedStartAt the planned start at value
   * @param ?DateTimeImmutable $dueAt the due at value
   *
   * @return self the create result
   */
  public static function create(
    string $id,
    string $organizationId,
    MissionType $type,
    string $name,
    string $referencePackId,
    ?string $siteId,
    ?string $responsibleId,
    array $participants,
    MissionPriority $priority,
    ?DateTimeImmutable $plannedStartAt,
    ?DateTimeImmutable $dueAt,
  ): self {
    $now = new DateTimeImmutable();
    $mission = new self(
      id: $id,
      organizationId: self::required($organizationId, 'Mission organization'),
      type: $type,
      name: self::normalizeName($name),
      status: MissionStatus::DRAFT,
      referencePackId: self::required($referencePackId, 'Mission reference pack'),
      siteId: self::nullable($siteId),
      responsibleId: self::nullable($responsibleId),
      participants: self::normalizeParticipants($participants),
      priority: $priority,
      plannedStartAt: $plannedStartAt,
      dueAt: $dueAt,
      reviewNote: null,
      revision: 1,
      createdAt: $now,
      updatedAt: $now,
    );
    $mission->assertSchedule();

    return $mission;
  }

  /**
   * Method reconstitute.
   *
   * @since 1.0.0
   *
   * @param string $id the id value
   * @param string $organizationId the organization id value
   * @param MissionType $type the type value
   * @param string $name the name value
   * @param MissionStatus $status the status value
   * @param string $referencePackId the reference pack id value
   * @param ?string $siteId the site id value
   * @param ?string $responsibleId the responsible id value
   * @param list<string> $participants
   * @param MissionPriority $priority the priority value
   * @param ?DateTimeImmutable $plannedStartAt the planned start at value
   * @param ?DateTimeImmutable $dueAt the due at value
   * @param ?string $reviewNote the review note value
   * @param int $revision the revision value
   * @param DateTimeImmutable $createdAt the created at value
   * @param DateTimeImmutable $updatedAt the updated at value
   *
   * @return self the reconstitute result
   */
  public static function reconstitute(
    string $id,
    string $organizationId,
    MissionType $type,
    string $name,
    MissionStatus $status,
    string $referencePackId,
    ?string $siteId,
    ?string $responsibleId,
    array $participants,
    MissionPriority $priority,
    ?DateTimeImmutable $plannedStartAt,
    ?DateTimeImmutable $dueAt,
    ?string $reviewNote,
    int $revision,
    DateTimeImmutable $createdAt,
    DateTimeImmutable $updatedAt,
  ): self {
    return new self(
      $id,
      $organizationId,
      $type,
      $name,
      $status,
      $referencePackId,
      $siteId,
      $responsibleId,
      $participants,
      $priority,
      $plannedStartAt,
      $dueAt,
      $reviewNote,
      $revision,
      $createdAt,
      $updatedAt,
    );
  }

  /**
   * Method rename.
   *
   * Executes the rename operation.
   *
   * @since 1.0.0
   *
   * @param string $name the name value
   */
  public function rename(string $name): void
  {
    $this->assertMutable();
    $this->name = self::normalizeName($name);
    $this->touch();
  }

  /**
   * Method changeReferencePack.
   *
   * Executes the change reference pack operation.
   *
   * @since 1.0.0
   *
   * @param string $referencePackId the reference pack id value
   */
  public function changeReferencePack(string $referencePackId): void
  {
    $this->assertPlanningMutable();
    $this->referencePackId = self::required($referencePackId, 'Mission reference pack');
    $this->touch();
  }

  /**
   * Method changeSite.
   *
   * Executes the change site operation.
   *
   * @since 1.0.0
   *
   * @param ?string $siteId the site id value
   */
  public function changeSite(?string $siteId): void
  {
    $this->assertPlanningMutable();
    $this->siteId = self::nullable($siteId);
    $this->touch();
  }

  /**
   * Method changeResponsible.
   *
   * Executes the change responsible operation.
   *
   * @since 1.0.0
   *
   * @param ?string $responsibleId the responsible id value
   */
  public function changeResponsible(?string $responsibleId): void
  {
    $this->assertPlanningMutable();
    $this->responsibleId = self::nullable($responsibleId);
    $this->touch();
  }

  /**
   * Method changeParticipants.
   *
   * @since 1.0.0
   *
   * @param list<string> $participants
   */
  public function changeParticipants(array $participants): void
  {
    $this->assertPlanningMutable();
    $this->participants = self::normalizeParticipants($participants);
    $this->touch();
  }

  /**
   * Method changePriority.
   *
   * Executes the change priority operation.
   *
   * @since 1.0.0
   *
   * @param MissionPriority $priority the priority value
   */
  public function changePriority(MissionPriority $priority): void
  {
    $this->assertPlanningMutable();
    $this->priority = $priority;
    $this->touch();
  }

  /**
   * Method reschedule.
   *
   * Executes the reschedule operation.
   *
   * @since 1.0.0
   *
   * @param ?DateTimeImmutable $plannedStartAt the planned start at value
   * @param ?DateTimeImmutable $dueAt the due at value
   */
  public function reschedule(?DateTimeImmutable $plannedStartAt, ?DateTimeImmutable $dueAt): void
  {
    $this->assertPlanningMutable();
    $this->plannedStartAt = $plannedStartAt;
    $this->dueAt = $dueAt;
    $this->assertSchedule();
    $this->touch();
  }

  /**
   * Method changeReviewNote.
   *
   * Executes the change review note operation.
   *
   * @since 1.0.0
   *
   * @param ?string $reviewNote the review note value
   */
  public function changeReviewNote(?string $reviewNote): void
  {
    $this->assertMutable();
    $this->reviewNote = self::nullable($reviewNote);
    $this->touch();
  }

  /**
   * Method transitionTo.
   *
   * Executes the transition to operation.
   *
   * @since 1.0.0
   *
   * @param MissionStatus $status the status value
   * @param MissionTransitionPolicy $policy the policy value
   */
  public function transitionTo(MissionStatus $status, MissionTransitionPolicy $policy): void
  {
    $this->assertMutable();
    $this->applyTransition($status, $policy);
    $this->touch();
  }

  /**
   * Method edit.
   *
   * @since 1.0.0
   *
   * @param MissionTransitionPolicy $policy the policy value
   * @param ?string $name the name value
   * @param ?string $referencePackId the reference pack id value
   * @param ?string $siteId the site id value
   * @param ?string $responsibleId the responsible id value
   * @param list<string>|null $participants
   * @param ?MissionPriority $priority the priority value
   * @param ?DateTimeImmutable $plannedStartAt the planned start at value
   * @param ?DateTimeImmutable $dueAt the due at value
   * @param ?string $reviewNote the review note value
   * @param ?MissionStatus $nextStatus the next status value
   * @param bool $hasName the has name value
   * @param bool $hasReferencePackId the has reference pack id value
   * @param bool $hasSiteId the has site id value
   * @param bool $hasResponsibleId the has responsible id value
   * @param bool $hasParticipants the has participants value
   * @param bool $hasPriority the has priority value
   * @param bool $hasPlannedStartAt the has planned start at value
   * @param bool $hasDueAt the has due at value
   * @param bool $hasReviewNote the has review note value
   */
  public function edit(
    MissionTransitionPolicy $policy,
    ?string $name = null,
    ?string $referencePackId = null,
    ?string $siteId = null,
    ?string $responsibleId = null,
    ?array $participants = null,
    ?MissionPriority $priority = null,
    ?DateTimeImmutable $plannedStartAt = null,
    ?DateTimeImmutable $dueAt = null,
    ?string $reviewNote = null,
    ?MissionStatus $nextStatus = null,
    bool $hasName = false,
    bool $hasReferencePackId = false,
    bool $hasSiteId = false,
    bool $hasResponsibleId = false,
    bool $hasParticipants = false,
    bool $hasPriority = false,
    bool $hasPlannedStartAt = false,
    bool $hasDueAt = false,
    bool $hasReviewNote = false,
  ): void {
    $this->assertMutable();
    if ($hasReferencePackId || $hasSiteId || $hasResponsibleId || $hasParticipants || $hasPriority || $hasPlannedStartAt || $hasDueAt) {
      $this->assertPlanningMutable();
    }
    if ($hasName) {
      $this->name = self::normalizeName($name ?? '');
    }
    if ($hasReferencePackId) {
      $this->referencePackId = self::required($referencePackId ?? '', 'Mission reference pack');
    }
    if ($hasSiteId) {
      $this->siteId = self::nullable($siteId);
    }
    if ($hasResponsibleId) {
      $this->responsibleId = self::nullable($responsibleId);
    }
    if ($hasParticipants) {
      $this->participants = self::normalizeParticipants($participants ?? []);
    }
    if ($hasPriority && $priority instanceof MissionPriority) {
      $this->priority = $priority;
    }
    if ($hasPlannedStartAt) {
      $this->plannedStartAt = $plannedStartAt;
    }
    if ($hasDueAt) {
      $this->dueAt = $dueAt;
    }
    if ($hasReviewNote) {
      $this->reviewNote = self::nullable($reviewNote);
    }
    $this->assertSchedule();
    if ($nextStatus instanceof MissionStatus) {
      $this->applyTransition($nextStatus, $policy);
    }
    $this->touch();
  }

  /**
   * Method id.
   *
   * Executes the id operation.
   *
   * @since 1.0.0
   *
   * @return string the id result
   */
  public function id(): string
  {
    return $this->id;
  }

  /**
   * Method organizationId.
   *
   * Executes the organization id operation.
   *
   * @since 1.0.0
   *
   * @return string the organization id result
   */
  public function organizationId(): string
  {
    return $this->organizationId;
  }

  /**
   * Method type.
   *
   * Executes the type operation.
   *
   * @since 1.0.0
   *
   * @return MissionType the type result
   */
  public function type(): MissionType
  {
    return $this->type;
  }

  /**
   * Method name.
   *
   * Executes the name operation.
   *
   * @since 1.0.0
   *
   * @return string the name result
   */
  public function name(): string
  {
    return $this->name;
  }

  /**
   * Method status.
   *
   * Executes the status operation.
   *
   * @since 1.0.0
   *
   * @return MissionStatus the status result
   */
  public function status(): MissionStatus
  {
    return $this->status;
  }

  /**
   * Method referencePackId.
   *
   * Executes the reference pack id operation.
   *
   * @since 1.0.0
   *
   * @return string the reference pack id result
   */
  public function referencePackId(): string
  {
    return $this->referencePackId;
  }

  /**
   * Method siteId.
   *
   * Executes the site id operation.
   *
   * @since 1.0.0
   *
   * @return ?string the site id result
   */
  public function siteId(): ?string
  {
    return $this->siteId;
  }

  /**
   * Method responsibleId.
   *
   * Executes the responsible id operation.
   *
   * @since 1.0.0
   *
   * @return ?string the responsible id result
   */
  public function responsibleId(): ?string
  {
    return $this->responsibleId;
  }

  /**
   * Method participants.
   *
   * @since 1.0.0
   *
   * @return list<string>
   */
  public function participants(): array
  {
    return $this->participants;
  }

  /**
   * Method priority.
   *
   * Executes the priority operation.
   *
   * @since 1.0.0
   *
   * @return MissionPriority the priority result
   */
  public function priority(): MissionPriority
  {
    return $this->priority;
  }

  /**
   * Method plannedStartAt.
   *
   * Executes the planned start at operation.
   *
   * @since 1.0.0
   *
   * @return ?DateTimeImmutable the planned start at result
   */
  public function plannedStartAt(): ?DateTimeImmutable
  {
    return $this->plannedStartAt;
  }

  /**
   * Method dueAt.
   *
   * Executes the due at operation.
   *
   * @since 1.0.0
   *
   * @return ?DateTimeImmutable the due at result
   */
  public function dueAt(): ?DateTimeImmutable
  {
    return $this->dueAt;
  }

  /**
   * Method reviewNote.
   *
   * Executes the review note operation.
   *
   * @since 1.0.0
   *
   * @return ?string the review note result
   */
  public function reviewNote(): ?string
  {
    return $this->reviewNote;
  }

  /**
   * Method revision.
   *
   * Executes the revision operation.
   *
   * @since 1.0.0
   *
   * @return int the revision result
   */
  public function revision(): int
  {
    return $this->revision;
  }

  /**
   * Method createdAt.
   *
   * Executes the created at operation.
   *
   * @since 1.0.0
   *
   * @return DateTimeImmutable the created at result
   */
  public function createdAt(): DateTimeImmutable
  {
    return $this->createdAt;
  }

  /**
   * Method updatedAt.
   *
   * Executes the updated at operation.
   *
   * @since 1.0.0
   *
   * @return DateTimeImmutable the updated at result
   */
  public function updatedAt(): DateTimeImmutable
  {
    return $this->updatedAt;
  }

  /**
   * Method applyTransition.
   *
   * Executes the apply transition operation.
   *
   * @since 1.0.0
   *
   * @param MissionStatus $status the status value
   * @param MissionTransitionPolicy $policy the policy value
   */
  private function applyTransition(MissionStatus $status, MissionTransitionPolicy $policy): void
  {
    $policy->assertAllowed($this->status, $status);
    if (MissionStatus::PLANNED === $status && (
      null === $this->siteId
      || null === $this->responsibleId
      || null === $this->plannedStartAt
      || null === $this->dueAt
    )) {
      throw new MissionConflictException('A site, responsible member, planned start and due date are required before planning a mission.');
    }
    $this->assertSchedule();
    if (MissionStatus::CHANGES_REQUESTED === $status && null === $this->reviewNote) {
      throw new MissionConflictException('A review note is required when requesting changes.');
    }
    $this->status = $status;
  }

  /**
   * Method assertMutable.
   *
   * Executes the assert mutable operation.
   *
   * @since 1.0.0
   */
  private function assertMutable(): void
  {
    if (!$this->status->isMutable()) {
      throw new MissionConflictException('Published or abandoned missions are immutable.');
    }
  }

  /**
   * Method assertPlanningMutable.
   *
   * Executes the assert planning mutable operation.
   *
   * @since 1.0.0
   */
  private function assertPlanningMutable(): void
  {
    $this->assertMutable();
    if (MissionStatus::DRAFT !== $this->status) {
      throw new MissionConflictException('Prepared scope and planning details are frozen after planning.');
    }
  }

  /**
   * Method assertSchedule.
   *
   * Executes the assert schedule operation.
   *
   * @since 1.0.0
   */
  private function assertSchedule(): void
  {
    if (null !== $this->plannedStartAt && null !== $this->dueAt && $this->dueAt <= $this->plannedStartAt) {
      throw new MissionConflictException('Mission due date must be after its planned start.');
    }
  }

  /**
   * Method touch.
   *
   * Executes the touch operation.
   *
   * @since 1.0.0
   */
  private function touch(): void
  {
    ++$this->revision;
    $this->updatedAt = new DateTimeImmutable();
  }

  /**
   * Method required.
   *
   * Executes the required operation.
   *
   * @since 1.0.0
   *
   * @param string $value the value value
   * @param string $field the field value
   *
   * @return string the required result
   */
  private static function required(string $value, string $field): string
  {
    $value = trim($value);
    if ('' === $value) {
      throw new MissionConflictException($field . ' is required.');
    }

    return $value;
  }

  /**
   * Method normalizeName.
   *
   * Executes the normalize name operation.
   *
   * @since 1.0.0
   *
   * @param string $name the name value
   *
   * @return string the normalize name result
   */
  private static function normalizeName(string $name): string
  {
    $name = self::required($name, 'Mission name');
    if (mb_strlen($name) > 160) {
      throw new MissionConflictException('Mission name must be at most 160 characters.');
    }

    return $name;
  }

  /**
   * Method nullable.
   *
   * Executes the nullable operation.
   *
   * @since 1.0.0
   *
   * @param ?string $value the value value
   *
   * @return ?string the nullable result
   */
  private static function nullable(?string $value): ?string
  {
    if (null === $value) {
      return null;
    }
    $value = trim($value);

    return '' === $value ? null : $value;
  }

  /**
   * Method normalizeParticipants.
   *
   * @since 1.0.0
   *
   * @param list<string> $participants the participants value
   *
   * @return list<string>
   */
  private static function normalizeParticipants(array $participants): array
  {
    /** @var list<string> */
    return array_values(array_unique($participants));
  }
}
