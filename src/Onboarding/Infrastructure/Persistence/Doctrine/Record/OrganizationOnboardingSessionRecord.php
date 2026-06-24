<?php

declare(strict_types=1);

namespace Onboarding\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Record OrganizationOnboardingSessionRecord.
 *
 * @category Record
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ORM\Entity]
#[ORM\Table(name: 'organization_onboarding_sessions')]
#[ORM\UniqueConstraint(name: 'uniq_onboarding_org_session_user', columns: ['user_id'])]
#[ORM\Index(name: 'idx_onboarding_org_session_state', columns: ['state'])]
#[ORM\Index(name: 'idx_onboarding_org_session_target_org', columns: ['target_organization_id'])]
class OrganizationOnboardingSessionRecord
{
  // #region Properties
  /**
   * Property id.
   *
   * @since 1.0.0
   */
  #[ORM\Id]
  #[ORM\Column(type: 'string', length: 36)]
  public string $id;

  /**
   * Property userId.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'user_id', type: 'string', length: 36)]
  public string $userId;

  /**
   * Property flow.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'flow', type: 'string', length: 32)]
  public string $flow = 'organization';

  /**
   * Property state.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'state', type: 'string', length: 20)]
  public string $state = 'in_progress';

  /**
   * Property nextStep.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'next_step', type: 'string', length: 64, nullable: true)]
  public ?string $nextStep = null;

  /**
   * Property blockedReason.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'blocked_reason', type: 'string', length: 120, nullable: true)]
  public ?string $blockedReason = null;

  /**
   * Property targetOrganizationId.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'target_organization_id', type: 'string', length: 36, nullable: true)]
  public ?string $targetOrganizationId = null;

  /**
   * Property targetOrganizationName.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'target_organization_name', type: 'string', length: 120, nullable: true)]
  public ?string $targetOrganizationName = null;

  /**
   * Property completedSteps.
   *
   * @since 1.0.0
   *
   * @var list<string>
   */
  #[ORM\Column(name: 'completed_steps', type: 'json')]
  public array $completedSteps = [];

  /**
   * Property skippedSteps.
   *
   * @since 2.0.0
   *
   * @var list<string>
   */
  #[ORM\Column(name: 'skipped_steps', type: 'json')]
  public array $skippedSteps = [];

  /**
   * Property rollbackStack.
   *
   * @since 1.0.0
   *
   * @var list<array<string, mixed>>
   */
  #[ORM\Column(name: 'rollback_stack', type: 'json')]
  public array $rollbackStack = [];

  /**
   * Property stepHistory.
   *
   * Ordered audit log of step completions and voluntary skips.
   *
   * @since 2.0.0
   *
   * @var list<array{stepKey: string, occurredAt: string, skipped: bool}>
   */
  #[ORM\Column(name: 'step_history', type: 'json')]
  public array $stepHistory = [];

  /**
   * Property createdAt.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
  public DateTimeImmutable $createdAt;

  /**
   * Property updatedAt.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
  public DateTimeImmutable $updatedAt;

  /**
   * Property dismissedAt.
   *
   * When the user voluntarily hid the non-blocking activation flow, or null.
   *
   * @since 3.0.0
   */
  #[ORM\Column(name: 'dismissed_at', type: 'datetime_immutable', nullable: true)]
  public ?DateTimeImmutable $dismissedAt = null;
  // #endregion
}
