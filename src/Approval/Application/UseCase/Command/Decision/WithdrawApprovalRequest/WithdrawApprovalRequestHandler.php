<?php

declare(strict_types=1);

namespace Approval\Application\UseCase\Command\Decision\WithdrawApprovalRequest;

use Approval\Application\Contract\Event\ApprovalWithdrawnEvent;
use Approval\Application\Port\Outbound\{ApprovalDecisionLockPort, ApprovalMemberDirectoryPort, ApprovalRequestRepositoryPort};
use Approval\Domain\Event\Request\ApprovalExpiredEvent;
use Approval\Domain\Exception\{
  ApprovalRequestNotFoundException,
  ApprovalRequestNotPendingException,
  ApprovalWithdrawalNotAllowedException
};
use Approval\Domain\ValueObject\ApprovalRequestId;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\{ClockPort, EventDispatcherPort};
use Throwable;

/**
 * UseCase WithdrawApprovalRequestHandler.
 *
 * Only an active requester may withdraw. The deferred action is never executed.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class WithdrawApprovalRequestHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param ApprovalRequestRepositoryPort $requests the approval request repository port
   * @param ApprovalMemberDirectoryPort $memberDirectory the cross-module member directory port
   * @param OrganizationAuthorizationPort $authorization the organization authorization port
   * @param EventDispatcherPort $eventDispatcher the domain event dispatcher
   * @param ClockPort $clock the clock port
   * @param ApprovalDecisionLockPort $decisions the transactional decision lock
   */
  public function __construct(
    private ApprovalRequestRepositoryPort $requests,
    private ApprovalMemberDirectoryPort $memberDirectory,
    private OrganizationAuthorizationPort $authorization,
    private EventDispatcherPort $eventDispatcher,
    private ClockPort $clock,
    private ApprovalDecisionLockPort $decisions,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param WithdrawApprovalRequestCommand $command the command payload
   *
   * @return WithdrawApprovalRequestResult the use case result
   */
  public function __invoke(WithdrawApprovalRequestCommand $command): WithdrawApprovalRequestResult
  {
    $result = $this->decisions->synchronized($command->requestId, fn () => $this->decide($command));
    if ($result instanceof Throwable) {
      throw $result;
    }

    return $result;
  }

  /**
   * Method decide.
   *
   * @since 1.0.0
   *
   * @param WithdrawApprovalRequestCommand $command the decision payload
   *
   * @return WithdrawApprovalRequestResult|ApprovalRequestNotPendingException the committed result or business refusal
   */
  private function decide(WithdrawApprovalRequestCommand $command): WithdrawApprovalRequestResult|ApprovalRequestNotPendingException
  {
    $request = $this->requests->findById(ApprovalRequestId::fromString($command->requestId));

    if (null === $request || $request->organizationId() !== $command->organizationId) {
      throw ApprovalRequestNotFoundException::withId($command->requestId);
    }

    if (!$this->authorization->isMemberOf($command->actorUserId, $command->organizationId)) {
      throw ApprovalRequestNotFoundException::withId($command->requestId);
    }
    $approverMemberId = $this->memberDirectory->resolveMemberId($command->organizationId, $command->actorUserId);
    if (null === $approverMemberId) {
      throw ApprovalRequestNotFoundException::withId($command->requestId);
    }
    if ($request->requestedByUserId() !== $command->actorUserId) {
      throw ApprovalWithdrawalNotAllowedException::create();
    }

    if (!$request->isPending()) {
      throw ApprovalRequestNotPendingException::withId($command->requestId);
    }

    $now = $this->clock->now();
    if ($request->expiresAt() <= $now) {
      $request->expire($now);
      $this->requests->save($request);
      $this->eventDispatcher->dispatch(new ApprovalExpiredEvent(
        organizationId: $request->organizationId(),
        requestId: (string) $request->id(),
        actionType: $request->actionType(),
        subjectId: $request->subjectId(),
      ));

      return ApprovalRequestNotPendingException::withId($command->requestId);
    }

    $request->withdraw($approverMemberId, $command->actorUserId, $command->decisionNote, $now);
    $this->requests->save($request);

    $this->eventDispatcher->dispatch(new ApprovalWithdrawnEvent(
      organizationId: $request->organizationId(),
      requestId: (string) $request->id(),
      actionType: $request->actionType(),
      subjectId: $request->subjectId(),
      decisionByMemberId: $approverMemberId,
      decisionByUserId: $command->actorUserId,
      occurredAt: $now,
    ));

    return WithdrawApprovalRequestResult::fromDomain($request);
  }
  // #endregion
}
