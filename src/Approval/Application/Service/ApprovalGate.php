<?php

declare(strict_types=1);

namespace Approval\Application\Service;

use Approval\Application\Contract\Gate\{ApprovalGateDecision, ApprovalGateRequest};
use Approval\Application\Port\Inbound\ApprovalGatePort;
use Approval\Application\Port\Outbound\{ApprovalMemberDirectoryPort, ApprovalPolicyPort, ApprovalRequestRepositoryPort};
use Approval\Domain\Event\Request\ApprovalRequestedEvent;
use Approval\Domain\ValueObject\{ApprovalRequestId, ApprovalStatus};
use DateInterval;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Domain\Exception\OrganizationAccessDeniedException;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Port\Outbound\{ClockPort, EventDispatcherPort};

use function is_string;
use function max;

/**
 * Service ApprovalGate.
 *
 * The interception seam (implements the inbound {@see ApprovalGatePort}):
 * resolves the organization's approval policy, decides whether a regulated
 * action may apply immediately, and — when it must be deferred — idempotently
 * reserves a pending {@see \Approval\Domain\Model\ApprovalRequest\ApprovalRequest}
 * and dispatches {@see ApprovalRequestedEvent}. Never runs on the approved
 * re-execution path (the owning modules' executor adapters re-dispatch their
 * existing command directly), so no bypass flag is ever needed.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ApprovalGate implements ApprovalGatePort
{
  // #region Constants
  /**
   * Ordinal ranking of non-conformity severities, low to critical — a local
   * business rule of the approval gate; the Approval module never depends
   * on the Inspection domain's severity enum.
   *
   * @var array<string, int>
   */
  private const array SEVERITY_ORDER = ['low' => 0, 'medium' => 1, 'high' => 2, 'critical' => 3];
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param ApprovalPolicyPort $policy the cross-module approval policy port
   * @param ApprovalRequestRepositoryPort $requests the approval request repository port
   * @param ApprovalMemberDirectoryPort $memberDirectory the cross-module member directory port
   * @param OrganizationAuthorizationPort $authorization the organization authorization port
   * @param EventDispatcherPort $eventDispatcher the domain event dispatcher
   * @param ClockPort $clock the clock port
   * @param UuidFactory $uuidFactory the uuid factory
   */
  public function __construct(
    private ApprovalPolicyPort $policy,
    private ApprovalRequestRepositoryPort $requests,
    private ApprovalMemberDirectoryPort $memberDirectory,
    private OrganizationAuthorizationPort $authorization,
    private EventDispatcherPort $eventDispatcher,
    private ClockPort $clock,
    private UuidFactory $uuidFactory,
  ) {
  }
  // #endregion

  // #region Methods
  public function evaluate(ApprovalGateRequest $request): ApprovalGateDecision
  {
    $policy = $this->policy->policyFor($request->organizationId);

    if (!$policy->isEnabledFor($request->actionType)) {
      return ApprovalGateDecision::applyNow();
    }

    $minSeverity = $policy->minSeverityFor($request->actionType);
    if (null !== $minSeverity && $this->belowSeverityThreshold($request->payload, $minSeverity)) {
      return ApprovalGateDecision::applyNow();
    }

    $this->authorization->assertGrantedPermissions($request->requestedByUserId, $request->organizationId, [
      'organization.approvals.request',
    ]);

    $requesterMemberId = $this->memberDirectory->resolveMemberId($request->organizationId, $request->requestedByUserId);
    if (null === $requesterMemberId) {
      throw OrganizationAccessDeniedException::missingPermission('organization.approvals.request');
    }

    $now = $this->clock->now();
    $expiresAt = $now->add(new DateInterval('P' . max(1, $policy->approvalTtlDays) . 'D'));

    /** @var ApprovalRequestId $id */
    $id = $this->uuidFactory->create(ApprovalRequestId::class);

    $reservation = $this->requests->reservePending(
      id: (string) $id,
      organizationId: $request->organizationId,
      actionType: $request->actionType,
      subjectId: $request->subjectId,
      requestedByMemberId: $requesterMemberId,
      requestedByUserId: $request->requestedByUserId,
      payload: $request->payload,
      expiresAt: $expiresAt,
      now: $now,
    );

    if (!$reservation->isNew) {
      $existing = $this->requests->findById(ApprovalRequestId::fromString($reservation->id));

      if (null !== $existing) {
        return ApprovalGateDecision::deferred($reservation->id, $existing->status()->value, $existing->expiresAt());
      }
    }

    $this->eventDispatcher->dispatch(new ApprovalRequestedEvent(
      organizationId: $request->organizationId,
      requestId: $reservation->id,
      actionType: $request->actionType,
      subjectId: $request->subjectId,
      requestedByMemberId: $requesterMemberId,
      requestedByUserId: $request->requestedByUserId,
    ));

    return ApprovalGateDecision::deferred($reservation->id, ApprovalStatus::PENDING->value, $expiresAt);
  }

  /**
   * Method belowSeverityThreshold.
   *
   * Reports whether the payload's `severity` entry ranks strictly below the
   * policy's minimum severity threshold. An unknown/missing severity never
   * counts as "below" — the gate fails closed (approval still required)
   * rather than silently skipping it.
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $payload the gate request payload
   * @param string $minSeverity the minimum severity threshold
   *
   * @return bool true when the payload's severity is strictly below the threshold
   */
  private function belowSeverityThreshold(array $payload, string $minSeverity): bool
  {
    $severity = $payload['severity'] ?? null;

    if (!is_string($severity)) {
      return false;
    }

    $severityRank = self::SEVERITY_ORDER[$severity] ?? null;
    $thresholdRank = self::SEVERITY_ORDER[$minSeverity] ?? null;

    if (null === $severityRank || null === $thresholdRank) {
      return false;
    }

    return $severityRank < $thresholdRank;
  }
  // #endregion
}
