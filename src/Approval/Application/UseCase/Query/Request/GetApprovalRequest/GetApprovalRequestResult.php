<?php

declare(strict_types=1);

namespace Approval\Application\UseCase\Query\Request\GetApprovalRequest;

use Approval\Domain\Model\ApprovalRequest\ApprovalRequest;
use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase GetApprovalRequestResult.
 *
 * Reused as the per-item view by `ListApprovalRequestsResult`, mirroring how
 * `Webhook\Application\UseCase\Query\Subscription\GetWebhookSubscription\GetWebhookSubscriptionResult`
 * is reused inside `ListWebhookSubscriptionsResult`.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetApprovalRequestResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $id the approval request identifier
   * @param string $organizationId the owning organization identifier
   * @param string $actionType the regulated action type
   * @param string $subjectId the acted-upon subject identifier
   * @param string $status the current status
   * @param string $requestedByMemberId the requesting member identifier
   * @param string $requestedByUserId the requesting user identifier
   * @param ?string $decisionByMemberId the deciding member identifier, once decided
   * @param ?string $decisionByUserId the deciding user identifier, once decided
   * @param ?string $decisionNote the free-form decision note
   * @param DateTimeImmutable $expiresAt the expiry deadline
   * @param DateTimeImmutable $createdAt the creation timestamp
   * @param DateTimeImmutable $updatedAt the last update timestamp
   * @param ?DateTimeImmutable $decidedAt the decision timestamp, once decided
   * @param ?DateTimeImmutable $executedAt the execution timestamp, once executed
   * @param ?string $executionError the last execution/cancellation error, if any
   */
  public function __construct(
    public string $id,
    public string $organizationId,
    public string $actionType,
    public string $subjectId,
    public string $status,
    public string $requestedByMemberId,
    public string $requestedByUserId,
    public ?string $decisionByMemberId,
    public ?string $decisionByUserId,
    public ?string $decisionNote,
    public DateTimeImmutable $expiresAt,
    public DateTimeImmutable $createdAt,
    public DateTimeImmutable $updatedAt,
    public ?DateTimeImmutable $decidedAt,
    public ?DateTimeImmutable $executedAt,
    public ?string $executionError,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method fromDomain.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @param ApprovalRequest $request the approval request aggregate
   *
   * @return self the view built from the aggregate
   */
  public static function fromDomain(ApprovalRequest $request): self
  {
    return new self(
      id: (string) $request->id(),
      organizationId: $request->organizationId(),
      actionType: $request->actionType(),
      subjectId: $request->subjectId(),
      status: $request->status()->value,
      requestedByMemberId: $request->requestedByMemberId(),
      requestedByUserId: $request->requestedByUserId(),
      decisionByMemberId: $request->decisionByMemberId(),
      decisionByUserId: $request->decisionByUserId(),
      decisionNote: $request->decisionNote(),
      expiresAt: $request->expiresAt(),
      createdAt: $request->createdAt(),
      updatedAt: $request->updatedAt(),
      decidedAt: $request->decidedAt(),
      executedAt: $request->executedAt(),
      executionError: $request->executionError(),
    );
  }
  // #endregion
}
