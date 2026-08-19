<?php

declare(strict_types=1);

namespace Intervention\Domain\Model\Intervention;

use DateTimeImmutable;
use Intervention\Domain\Exception\InterventionConflictException;
use Intervention\Domain\Service\{InterventionMutabilityPolicy, InterventionTransitionPolicy};
use Intervention\Domain\ValueObject\{InterventionPriority, InterventionStatus, InterventionType};

use function array_unique;
use function array_values;
use function mb_strlen;
use function sprintf;
use function trim;

/**
 * Domain Intervention.
 *
 * @category Domain
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class Intervention
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $id the id value
   * @param string $organizationId the organization id value
   * @param InterventionType $type the type value
   * @param string $name the name value
   * @param InterventionStatus $status the status value
   * @param ?string $siteId the site id value
   * @param ?string $responsibleId the responsible id value
   * @param list<string> $participants
   * @param InterventionPriority $priority the priority value
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
    private InterventionType $type,
    private string $name,
    private ?string $description,
    private InterventionStatus $status,
    private ?string $siteId,
    private ?string $responsibleId,
    private array $participants,
    private InterventionPriority $priority,
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
   * @param InterventionType $type the type value
   * @param string $name the name value
   * @param ?string $siteId the site id value
   * @param ?string $responsibleId the responsible id value
   * @param list<string> $participants
   * @param InterventionPriority $priority the priority value
   * @param ?DateTimeImmutable $plannedStartAt the planned start at value
   * @param ?DateTimeImmutable $dueAt the due at value
   * @param ?string $description the description value
   *
   * @return self the create result
   */
  public static function create(
    string $id,
    string $organizationId,
    InterventionType $type,
    string $name,
    ?string $siteId,
    ?string $responsibleId,
    array $participants,
    InterventionPriority $priority,
    ?DateTimeImmutable $plannedStartAt,
    ?DateTimeImmutable $dueAt,
    ?string $description = null,
  ): self {
    $now = new DateTimeImmutable();
    $intervention = new self(
      id: $id,
      organizationId: self::required($organizationId, 'Intervention organization'),
      type: $type,
      name: self::normalizeName($name),
      description: self::nullable($description),
      status: InterventionStatus::DRAFT,
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
    $intervention->assertSchedule();

    return $intervention;
  }

  /**
   * Method reconstitute.
   *
   * @since 1.0.0
   *
   * @param string $id the id value
   * @param string $organizationId the organization id value
   * @param InterventionType $type the type value
   * @param string $name the name value
   * @param ?string $description the description value
   * @param InterventionStatus $status the status value
   * @param ?string $siteId the site id value
   * @param ?string $responsibleId the responsible id value
   * @param list<string> $participants
   * @param InterventionPriority $priority the priority value
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
    InterventionType $type,
    string $name,
    ?string $description,
    InterventionStatus $status,
    ?string $siteId,
    ?string $responsibleId,
    array $participants,
    InterventionPriority $priority,
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
      $description,
      $status,
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
   * Method changeDescription.
   *
   * Executes the change description operation. Unlike planning fields, the
   * free-text description stays editable across every non-terminal status.
   *
   * @since 1.0.0
   *
   * @param ?string $description the description value
   */
  public function changeDescription(?string $description): void
  {
    $this->assertMutable();
    $this->description = self::nullable($description);
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
   * @param InterventionStatus $status the status value
   * @param InterventionTransitionPolicy $policy the policy value
   */
  public function transitionTo(InterventionStatus $status, InterventionTransitionPolicy $policy): void
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
   * @param InterventionTransitionPolicy $policy the policy value
   * @param ?string $name the name value
   * @param ?string $siteId the site id value
   * @param ?string $responsibleId the responsible id value
   * @param list<string>|null $participants
   * @param ?InterventionPriority $priority the priority value
   * @param ?DateTimeImmutable $plannedStartAt the planned start at value
   * @param ?DateTimeImmutable $dueAt the due at value
   * @param ?string $reviewNote the review note value
   * @param ?InterventionStatus $nextStatus the next status value
   * @param bool $hasName the has name value
   * @param bool $hasDescription the has description value
   * @param bool $hasSiteId the has site id value
   * @param bool $hasResponsibleId the has responsible id value
   * @param bool $hasParticipants the has participants value
   * @param bool $hasPriority the has priority value
   * @param bool $hasPlannedStartAt the has planned start at value
   * @param bool $hasDueAt the has due at value
   * @param bool $hasReviewNote the has review note value
   */
  public function edit(
    InterventionTransitionPolicy $policy,
    ?string $name = null,
    ?string $description = null,
    ?string $siteId = null,
    ?string $responsibleId = null,
    ?array $participants = null,
    ?InterventionPriority $priority = null,
    ?DateTimeImmutable $plannedStartAt = null,
    ?DateTimeImmutable $dueAt = null,
    ?string $reviewNote = null,
    ?InterventionStatus $nextStatus = null,
    bool $hasName = false,
    bool $hasDescription = false,
    bool $hasSiteId = false,
    bool $hasResponsibleId = false,
    bool $hasParticipants = false,
    bool $hasPriority = false,
    bool $hasPlannedStartAt = false,
    bool $hasDueAt = false,
    bool $hasReviewNote = false,
  ): void {
    $this->assertMutable();
    if ($hasSiteId) {
      $this->assertScopeMutable();
    }
    if ($hasResponsibleId) {
      $this->assertOwnershipMutable();
    }
    if ($hasParticipants || $hasPriority || $hasPlannedStartAt || $hasDueAt) {
      $this->assertScheduleMutable();
    }
    if ($hasName) {
      $this->name = self::normalizeName($name ?? '');
    }
    if ($hasDescription) {
      $this->description = self::nullable($description);
    }
    if ($hasSiteId) {
      $this->siteId = self::nullable($siteId);
    }
    if ($hasResponsibleId) {
      $this->responsibleId = $this->keptAfterDraft(self::nullable($responsibleId), 'responsible member');
    }
    if ($hasParticipants) {
      $this->participants = self::normalizeParticipants($participants ?? []);
    }
    if ($hasPriority && $priority instanceof InterventionPriority) {
      $this->priority = $priority;
    }
    if ($hasPlannedStartAt) {
      $this->plannedStartAt = $this->keptAfterDraft($plannedStartAt, 'planned start');
    }
    if ($hasDueAt) {
      $this->dueAt = $this->keptAfterDraft($dueAt, 'due date');
    }
    if ($hasReviewNote) {
      $this->reviewNote = self::nullable($reviewNote);
    }
    $this->assertSchedule();
    if ($nextStatus instanceof InterventionStatus) {
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
   * @return InterventionType the type result
   */
  public function type(): InterventionType
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
   * Method description.
   *
   * Executes the description operation.
   *
   * @since 1.0.0
   *
   * @return ?string the description result
   */
  public function description(): ?string
  {
    return $this->description;
  }

  /**
   * Method status.
   *
   * Executes the status operation.
   *
   * @since 1.0.0
   *
   * @return InterventionStatus the status result
   */
  public function status(): InterventionStatus
  {
    return $this->status;
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
   * @return InterventionPriority the priority result
   */
  public function priority(): InterventionPriority
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
   * @param InterventionStatus $status the status value
   * @param InterventionTransitionPolicy $policy the policy value
   */
  private function applyTransition(InterventionStatus $status, InterventionTransitionPolicy $policy): void
  {
    $policy->assertAllowed($this->status, $status);
    if (InterventionStatus::PLANNED === $status && (
      null === $this->siteId
      || null === $this->responsibleId
      || null === $this->plannedStartAt
      || null === $this->dueAt
    )) {
      throw new InterventionConflictException('A site, responsible member, planned start and due date are required before planning an intervention.');
    }
    $this->assertSchedule();
    if (InterventionStatus::CHANGES_REQUESTED === $status && null === $this->reviewNote) {
      throw new InterventionConflictException('A review note is required when requesting changes.');
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
      throw new InterventionConflictException('Published or abandoned interventions are immutable.');
    }
  }

  /**
   * Method assertScopeMutable.
   *
   * The site scopes every prepared work item, so it is editable while drafting
   * only — changing it later would invalidate the prepared scope; recreating
   * the intervention is the honest gesture. Delegates the window itself to
   * {@see InterventionMutabilityPolicy} so the read-side action-capability
   * advertisement on `InterventionOutput` answers from the same source.
   *
   * @since 1.1.0
   */
  private function assertScopeMutable(): void
  {
    if (!new InterventionMutabilityPolicy()->isScopeMutable($this->status)) {
      throw new InterventionConflictException(
        $this->status->isMutable()
          ? 'The site is frozen after planning; create a new intervention to target another site.'
          : 'Published or abandoned interventions are immutable.',
      );
    }
  }

  /**
   * Method assertOwnershipMutable.
   *
   * The responsible member governs submission, withdrawal and work-item
   * execution rights, so a handover is only allowed while nothing has started:
   * draft and planned. Delegates the window itself to
   * {@see InterventionMutabilityPolicy} — see {@see self::assertScopeMutable()}.
   *
   * @since 1.1.0
   */
  private function assertOwnershipMutable(): void
  {
    if (!new InterventionMutabilityPolicy()->isOwnershipMutable($this->status)) {
      throw new InterventionConflictException(
        $this->status->isMutable()
          ? 'The responsible member is frozen once field work has started.'
          : 'Published or abandoned interventions are immutable.',
      );
    }
  }

  /**
   * Method assertScheduleMutable.
   *
   * Dates, priority and participants stay editable through planned,
   * in_progress and changes_requested — a delayed intervention is rescheduled,
   * not abandoned and recreated. Under review (`submitted`) everything is
   * frozen: withdraw first. Delegates the window itself to
   * {@see InterventionMutabilityPolicy} — see {@see self::assertScopeMutable()}.
   *
   * @since 1.1.0
   */
  private function assertScheduleMutable(): void
  {
    if (!new InterventionMutabilityPolicy()->isScheduleMutable($this->status)) {
      throw new InterventionConflictException(
        $this->status->isMutable()
          ? 'A submitted intervention is frozen while under review; withdraw it to replan.'
          : 'Published or abandoned interventions are immutable.',
      );
    }
  }

  /**
   * Method keptAfterDraft.
   *
   * Refuses clearing a planning value once the intervention left draft: the
   * `planned` preconditions only guard the transition into `planned`, so
   * without this a later merge-patch could null a date or the responsible and
   * leave a planned intervention unschedulable.
   *
   * @since 1.1.0
   *
   * @template T
   *
   * @param T|null $value the incoming value
   * @param string $field the field named in the error
   *
   * @return T|null the value, guaranteed non-null outside draft
   */
  private function keptAfterDraft(mixed $value, string $field): mixed
  {
    if (null === $value && InterventionStatus::DRAFT !== $this->status) {
      throw new InterventionConflictException(sprintf('A planned intervention keeps its %s; set another value instead of clearing it.', $field));
    }

    return $value;
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
      throw new InterventionConflictException('Intervention due date must be after its planned start.');
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
      throw new InterventionConflictException($field . ' is required.');
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
    $name = self::required($name, 'Intervention name');
    if (mb_strlen($name) > 160) {
      throw new InterventionConflictException('Intervention name must be at most 160 characters.');
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
