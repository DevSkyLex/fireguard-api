<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Organization\ChangeOrganizationPlan;

use Notification\Application\Contract\Notification\{NotificationChannel, SendNotificationRequest};
use Notification\Application\Contract\Notification\NotificationType;
use Notification\Application\Port\Inbound\NotificationPort;
use Organization\Application\Contract\Quota\OrganizationQuotaResource as QuotaResourceContract;
use Organization\Application\Port\Inbound\OrganizationQuotaPort;
use Organization\Application\Port\Outbound\{OrganizationRepositoryPort, PlanRepositoryPort};
use Organization\Domain\Event\Plan\OrganizationPlanChangedEvent;
use Organization\Domain\Exception\{OrganizationNotFoundException, OrganizationPlanUsageExceededException, PlanNotFoundException};
use Organization\Domain\Exception\PlanNotAvailableException;
use Organization\Domain\Model\Plan\Plan;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationQuotaResource, PlanId};
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\{EventDispatcherPort, LoggerPort, TransactionManagerPort};
use Throwable;
use User\Application\Port\Outbound\UserRepositoryPort;
use User\Domain\ValueObject\UserId;

use function array_keys;
use function implode;
use function sprintf;

/**
 * UseCase ChangeOrganizationPlanHandler.
 *
 * Assigns the organization to a subscription plan with a usage-aware
 * downgrade guard: when the organization's CURRENT usage exceeds the
 * candidate plan's caps, an unacknowledged change is refused (409) so the
 * self-service client can surface a confirmation step, while acknowledged
 * changes (confirmed downgrades and the Stripe webhook path, which is the
 * source of truth) are applied with the overage recorded in the audit event
 * and notified to the organization owner. Existing data is never blocked
 * retroactively — the per-resource create guards already refuse new
 * creations while usage stays above the caps.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ChangeOrganizationPlanHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the ChangeOrganizationPlanHandler class.
   *
   * @since 1.0.0
   *
   * @param OrganizationRepositoryPort $organizationRepository the organization repository port
   * @param PlanRepositoryPort $planRepository the plan repository port
   * @param OrganizationQuotaPort $quota the quota port measuring current usage
   * @param UserRepositoryPort $userRepository the user repository (owner notification)
   * @param NotificationPort $notificationPort the notification port
   * @param LoggerPort $logger the logger
   * @param TransactionManagerPort $transactionManager the transaction manager
   * @param EventDispatcherPort $eventDispatcher the event dispatcher
   */
  public function __construct(
    private OrganizationRepositoryPort $organizationRepository,
    private PlanRepositoryPort $planRepository,
    private OrganizationQuotaPort $quota,
    private UserRepositoryPort $userRepository,
    private NotificationPort $notificationPort,
    private LoggerPort $logger,
    private TransactionManagerPort $transactionManager,
    private EventDispatcherPort $eventDispatcher,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Assigns the organization to the requested subscription plan.
   *
   * @since 1.0.0
   *
   * @param ChangeOrganizationPlanCommand $command the command payload
   *
   * @return ChangeOrganizationPlanResult the use case result
   */
  public function __invoke(ChangeOrganizationPlanCommand $command): ChangeOrganizationPlanResult
  {
    $organization = $this->organizationRepository->findById(OrganizationId::fromString($command->organizationId));

    if (null === $organization) {
      throw OrganizationNotFoundException::withId($command->organizationId);
    }

    $planId = PlanId::fromString($command->planId);
    $plan = $this->planRepository->findById($planId);

    if (null === $plan) {
      throw PlanNotFoundException::withId($command->planId);
    }

    if (!$plan->isActive()) {
      throw PlanNotAvailableException::create();
    }

    $previousPlanId = $organization->planId();
    $previousPlanIdValue = null !== $previousPlanId ? (string) $previousPlanId : null;
    $isActualChange = $previousPlanIdValue !== (string) $planId;

    // Usage-aware downgrade guard: refuse an unacknowledged change whose caps
    // sit below the current usage. Re-applying the current plan is exempt —
    // the Stripe webhook legitimately re-sends subscription state, and a
    // no-op must never dead-end on its own overage.
    // ACCEPTED RACE (bounded): this read is not serialized with the advisory
    // locks the create guards take, so a create committing between the read
    // and the save can land the org over the new caps without the 409 or the
    // owner notification. Growth still stops immediately — every subsequent
    // create re-checks usage against the NEW plan under the lock — and the
    // over-quota state stays visible in the settings Usage tab.
    $violations = $isActualChange ? $this->usageViolations($command->organizationId, $plan) : [];
    if ([] !== $violations && !$command->acknowledgeOveruse) {
      throw OrganizationPlanUsageExceededException::withViolations($violations);
    }

    $organization->changePlan($planId);

    $this->transactionManager->transactional(function () use ($organization): void {
      $this->organizationRepository->save($organization);
    });

    if ($isActualChange) {
      $this->eventDispatcher->dispatch(new OrganizationPlanChangedEvent(
        organizationId: (string) $organization->id(),
        planId: (string) $planId,
        previousPlanId: $previousPlanIdValue,
        overQuotaResources: array_keys($violations),
      ));
    }

    if ([] !== $violations) {
      $this->notifyOwnerOfOverQuota($organization->ownerUserId(), (string) $organization->id(), (string) $organization->name(), $violations);
    }

    return new ChangeOrganizationPlanResult(
      organizationId: (string) $organization->id(),
      planId: (string) $planId,
    );
  }

  /**
   * Method usageViolations.
   *
   * Measures the organization's current usage against the candidate plan's
   * caps and returns every exceeded resource.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param Plan $plan the candidate plan
   *
   * @return array<string, array{usage: int, limit: int}> usage/limit per exceeded resource
   */
  private function usageViolations(string $organizationId, Plan $plan): array
  {
    $violations = [];

    foreach (OrganizationQuotaResource::cases() as $resource) {
      $limit = $plan->limitFor($resource);
      if (null === $limit) {
        continue;
      }

      $usage = $this->quota->getUsage($organizationId, QuotaResourceContract::from($resource->value));
      if ($usage > $limit) {
        $violations[$resource->value] = ['usage' => $usage, 'limit' => $limit];
      }
    }

    return $violations;
  }

  /**
   * Method notifyOwnerOfOverQuota.
   *
   * Notifies the organization owner that the applied plan's caps sit below
   * the current usage (grace path: nothing is deleted, new creations stay
   * blocked until usage fits the plan). Never blocks the plan change.
   *
   * @since 1.0.0
   *
   * @param string $ownerUserId the owner user identifier
   * @param string $organizationId the organization identifier
   * @param string $organizationName the organization name
   * @param array<string, array{usage: int, limit: int}> $violations usage/limit per exceeded resource
   */
  private function notifyOwnerOfOverQuota(string $ownerUserId, string $organizationId, string $organizationName, array $violations): void
  {
    $parts = [];
    foreach ($violations as $resource => $numbers) {
      $parts[] = sprintf('%s %d/%d', $resource, $numbers['usage'], $numbers['limit']);
    }

    try {
      $owner = $this->userRepository->findById(new UserId($ownerUserId));

      // The EMAIL channel requires a resolvable address: when the owner user
      // no longer exists (dangling ownerUserId), degrade to MERCURE only so
      // the in-app record still persists instead of the whole send aborting.
      $channels = [NotificationChannel::MERCURE];
      if (null !== $owner) {
        $channels[] = NotificationChannel::EMAIL;
      }

      $this->notificationPort->send(new SendNotificationRequest(
        type: NotificationType::ORGANIZATION_PLAN_OVER_QUOTA,
        subject: sprintf('%s exceeds its new plan limits', $organizationName),
        body: sprintf(
          'The plan applied to %s has lower limits than its current usage (%s). Existing data is preserved, but new creations stay blocked until usage fits the plan.',
          $organizationName,
          implode(', ', $parts),
        ),
        channels: $channels,
        payload: [
          'organizationId' => $organizationId,
          'overQuotaResources' => array_keys($violations),
        ],
        recipientUserId: $ownerUserId,
        recipientEmail: null !== $owner ? (string) $owner->email() : null,
        organizationId: $organizationId,
      ));
    } catch (Throwable $exception) {
      $this->logger->warning('Plan over-quota notification dispatch failed.', [
        'organizationId' => $organizationId,
        'ownerUserId' => $ownerUserId,
        'error' => $exception->getMessage(),
      ]);
    }
  }
  // #endregion
}
