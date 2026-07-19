<?php

declare(strict_types=1);

namespace Inspection\Infrastructure\Adapter\Approval;

use Approval\Application\Contract\Action\ApprovalActionTypes;
use Approval\Application\Contract\Execution\DeferredActionContext;
use Approval\Application\Port\Outbound\ApprovalActionExecutorPort;
use Approval\Domain\Exception\DeferredActionNoLongerApplicableException;
use Inspection\Application\UseCase\Command\NonConformity\UpdateNonConformityStatus\UpdateNonConformityStatusCommand;
use Inspection\Application\UseCase\Query\NonConformity\GetNonConformity\{GetNonConformityQuery, GetNonConformityResult};
use Inspection\Domain\Exception\{InspectionNotFoundException, NonConformityAlreadyResolvedException, NonConformityNotFoundException};
use Inspection\Domain\ValueObject\NonConformityStatus;
use Shared\Application\Exception\{MessengerExceptionUnwrapperTrait, MessengerRuntimeException};
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};
use Throwable;

use function is_string;

/**
 * Adapter NonConformityWaiverExecutorAdapter.
 *
 * Tagged `approval.deferred_action_executor` (action type `nc_waiver`):
 * re-dispatches the EXISTING `UpdateNonConformityStatusCommand` (status
 * `waived`) so the original handler re-validates the non-conformity's
 * current state. Because `NonConformity::updateStatus()` throws
 * `NonConformityAlreadyResolvedException` whether it was already `done` or
 * already `waived`, this adapter re-queries the non-conformity's current
 * status to distinguish the two: already-`waived` is a routine idempotent
 * outcome (a second approve attempt, or a request approved after the NC was
 * independently waived); already-`done` (or the inspection/NC no longer
 * existing) means the deferred action can no longer be applied.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class NonConformityWaiverExecutorAdapter implements ApprovalActionExecutorPort
{
  use MessengerExceptionUnwrapperTrait;

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus the command bus
   * @param QueryBusPort $queryBus the query bus
   */
  public function __construct(
    private CommandBusPort $commandBus,
    private QueryBusPort $queryBus,
  ) {
  }
  // #endregion

  // #region Methods
  public function actionType(): string
  {
    return ApprovalActionTypes::NC_WAIVER;
  }

  public function execute(DeferredActionContext $context): void
  {
    $inspectionId = self::stringPayload($context->payload, 'inspectionId');

    if (null === $inspectionId) {
      throw DeferredActionNoLongerApplicableException::becauseSubjectChanged('Missing inspectionId in the deferred action payload.');
    }

    try {
      $this->commandBus->dispatch(new UpdateNonConformityStatusCommand(
        organizationId: $context->organizationId,
        inspectionId: $inspectionId,
        nonConformityId: $context->subjectId,
        status: NonConformityStatus::WAIVED->value,
      ));
    } catch (MessengerRuntimeException $exception) {
      $this->handleFailure($exception, $context->organizationId, $inspectionId, $context->subjectId);
    }
  }

  /**
   * Method handleFailure.
   *
   * @since 1.0.0
   *
   * @param MessengerRuntimeException $exception the caught dispatch failure
   * @param string $organizationId the owning organization identifier
   * @param string $inspectionId the inspection identifier
   * @param string $nonConformityId the non-conformity identifier
   */
  private function handleFailure(MessengerRuntimeException $exception, string $organizationId, string $inspectionId, string $nonConformityId): void
  {
    if ($this->findException($exception, NonConformityAlreadyResolvedException::class) instanceof NonConformityAlreadyResolvedException) {
      if ($this->isAlreadyWaived($organizationId, $inspectionId, $nonConformityId)) {
        return;
      }

      throw DeferredActionNoLongerApplicableException::becauseSubjectChanged('The non-conformity was already resolved with a different outcome.');
    }

    $notFound = $this->findException($exception, NonConformityNotFoundException::class)
      ?? $this->findException($exception, InspectionNotFoundException::class);

    if ($notFound instanceof Throwable) {
      throw DeferredActionNoLongerApplicableException::becauseSubjectChanged($notFound->getMessage());
    }

    throw $exception;
  }

  /**
   * Method isAlreadyWaived.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the owning organization identifier
   * @param string $inspectionId the inspection identifier
   * @param string $nonConformityId the non-conformity identifier
   *
   * @return bool true when the non-conformity's current status is `waived`
   */
  private function isAlreadyWaived(string $organizationId, string $inspectionId, string $nonConformityId): bool
  {
    try {
      /** @var GetNonConformityResult $result */
      $result = $this->queryBus->ask(new GetNonConformityQuery(
        organizationId: $organizationId,
        inspectionId: $inspectionId,
        nonConformityId: $nonConformityId,
      ));
    } catch (Throwable) {
      return false;
    }

    return NonConformityStatus::WAIVED->value === $result->status;
  }

  /**
   * Method stringPayload.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $payload the deferred action payload
   * @param string $key the payload key value
   *
   * @return ?string the string value at the given payload key, or null
   */
  private static function stringPayload(array $payload, string $key): ?string
  {
    $value = $payload[$key] ?? null;

    return is_string($value) && '' !== $value ? $value : null;
  }
  // #endregion
}
