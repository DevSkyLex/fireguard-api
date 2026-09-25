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
  // #region Constants
  private const string IMMUTABLE_INTERVENTION_MESSAGE = 'Published or abandoned interventions are immutable.';
  // #endregion

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
   * @param InterventionCreation $creation the proposed intervention
   *
   * @return self the create result
   */
  public static function create(InterventionCreation $creation): self
  {
    $now = new DateTimeImmutable();
    $intervention = new self(
      id: $creation->id,
      organizationId: self::required($creation->organizationId, 'Intervention organization'),
      type: $creation->type,
      name: self::normalizeName($creation->content->name),
      description: self::nullable($creation->content->description),
      status: InterventionStatus::DRAFT,
      siteId: self::nullable($creation->ownership->siteId),
      responsibleId: self::nullable($creation->ownership->responsibleId),
      participants: self::normalizeParticipants($creation->ownership->participants),
      priority: $creation->schedule->priority,
      plannedStartAt: $creation->schedule->plannedStartAt,
      dueAt: $creation->schedule->dueAt,
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
   * @param InterventionRestoredState $state the persisted intervention state
   *
   * @return self the reconstitute result
   */
  public static function reconstitute(InterventionRestoredState $state): self
  {
    return new self(
      $state->creation->id,
      $state->creation->organizationId,
      $state->creation->type,
      $state->creation->content->name,
      $state->creation->content->description,
      $state->status,
      $state->creation->ownership->siteId,
      $state->creation->ownership->responsibleId,
      $state->creation->ownership->participants,
      $state->creation->schedule->priority,
      $state->creation->schedule->plannedStartAt,
      $state->creation->schedule->dueAt,
      $state->reviewNote,
      $state->revision,
      $state->createdAt,
      $state->updatedAt,
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
   * @param InterventionPatch $patch typed values and explicit presence flags
   */
  public function edit(InterventionTransitionPolicy $policy, InterventionPatch $patch): void
  {
    $text = $patch->text;
    $ownership = $patch->ownership;
    $schedule = $patch->schedule;
    $this->assertMutable();
    if ($ownership->hasSiteId) {
      $this->assertScopeMutable();
    }
    if ($ownership->hasResponsibleId) {
      $this->assertOwnershipMutable();
    }
    if ($ownership->hasParticipants || $schedule->hasPriority || $schedule->hasPlannedStartAt || $schedule->hasDueAt) {
      $this->assertScheduleMutable();
    }
    if ($text->hasName) {
      $this->name = self::normalizeName($text->name ?? '');
    }
    if ($text->hasDescription) {
      $this->description = self::nullable($text->description);
    }
    if ($ownership->hasSiteId) {
      $this->siteId = self::nullable($ownership->siteId);
    }
    if ($ownership->hasResponsibleId) {
      $this->responsibleId = $this->keptAfterDraft(self::nullable($ownership->responsibleId), 'responsible member');
    }
    if ($ownership->hasParticipants) {
      $this->participants = self::normalizeParticipants($ownership->participants ?? []);
    }
    if ($schedule->hasPriority && $schedule->priority instanceof InterventionPriority) {
      $this->priority = $schedule->priority;
    }
    if ($schedule->hasPlannedStartAt) {
      $this->plannedStartAt = $this->keptAfterDraft($schedule->plannedStartAt, 'planned start');
    }
    if ($schedule->hasDueAt) {
      $this->dueAt = $this->keptAfterDraft($schedule->dueAt, 'due date');
    }
    if ($text->hasReviewNote) {
      $this->reviewNote = self::nullable($text->reviewNote);
    }
    $this->assertSchedule();
    if ($patch->nextStatus instanceof InterventionStatus) {
      $this->applyTransition($patch->nextStatus, $policy);
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
      throw new InterventionConflictException(self::IMMUTABLE_INTERVENTION_MESSAGE);
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
          : self::IMMUTABLE_INTERVENTION_MESSAGE,
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
          : self::IMMUTABLE_INTERVENTION_MESSAGE,
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
          : self::IMMUTABLE_INTERVENTION_MESSAGE,
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
