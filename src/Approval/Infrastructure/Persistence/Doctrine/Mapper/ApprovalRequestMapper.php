<?php

declare(strict_types=1);

namespace Approval\Infrastructure\Persistence\Doctrine\Mapper;

use Approval\Domain\Model\ApprovalRequest\ApprovalRequest;
use Approval\Domain\ValueObject\{ApprovalRequestId, ApprovalStatus};
use Approval\Infrastructure\Persistence\Doctrine\Record\ApprovalRequestRecord;

/**
 * Mapper ApprovalRequestMapper.
 *
 * @category Mapper
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ApprovalRequestMapper
{
  // #region Methods
  /**
   * Method toDomain.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @param ApprovalRequestRecord $record the persistence record
   *
   * @return ApprovalRequest the domain aggregate
   */
  public static function toDomain(ApprovalRequestRecord $record): ApprovalRequest
  {
    return ApprovalRequest::reconstitute(
      id: ApprovalRequestId::fromString($record->id),
      organizationId: $record->organizationId,
      actionType: $record->actionType,
      subjectId: $record->subjectId,
      status: ApprovalStatus::from($record->status),
      requestedByMemberId: $record->requestedByMemberId,
      requestedByUserId: $record->requestedByUserId,
      decisionByMemberId: $record->decisionByMemberId,
      decisionByUserId: $record->decisionByUserId,
      decisionNote: $record->decisionNote,
      payload: $record->payload,
      expiresAt: $record->expiresAt,
      createdAt: $record->createdAt,
      updatedAt: $record->updatedAt,
      decidedAt: $record->decidedAt,
      executedAt: $record->executedAt,
      executionError: $record->executionError,
    );
  }

  /**
   * Method toRecord.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @param ApprovalRequest $request the domain aggregate
   * @param ApprovalRequestRecord $record the persistence record to populate
   */
  public static function toRecord(ApprovalRequest $request, ApprovalRequestRecord $record): void
  {
    $record->id = (string) $request->id();
    $record->organizationId = $request->organizationId();
    $record->actionType = $request->actionType();
    $record->subjectId = $request->subjectId();
    $record->status = $request->status()->value;
    $record->requestedByMemberId = $request->requestedByMemberId();
    $record->requestedByUserId = $request->requestedByUserId();
    $record->decisionByMemberId = $request->decisionByMemberId();
    $record->decisionByUserId = $request->decisionByUserId();
    $record->decisionNote = $request->decisionNote();
    $record->payload = $request->payload();
    $record->expiresAt = $request->expiresAt();
    $record->createdAt = $request->createdAt();
    $record->updatedAt = $request->updatedAt();
    $record->decidedAt = $request->decidedAt();
    $record->executedAt = $request->executedAt();
    $record->executionError = $request->executionError();
  }
  // #endregion
}
