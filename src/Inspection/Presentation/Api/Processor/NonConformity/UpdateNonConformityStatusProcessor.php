<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Processor\NonConformity;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Approval\Application\Contract\Action\ApprovalActionTypes;
use Approval\Application\Contract\Gate\ApprovalGateRequest;
use Approval\Application\Port\Inbound\ApprovalGatePort;
use Auth\Infrastructure\Security\User\SecurityUser;
use Inspection\Application\UseCase\Command\NonConformity\UpdateNonConformityStatus\{UpdateNonConformityStatusCommand, UpdateNonConformityStatusResult};
use Inspection\Application\UseCase\Query\NonConformity\GetNonConformity\{GetNonConformityQuery, GetNonConformityResult};
use Inspection\Domain\Exception\{InspectionNotFoundException, NonConformityAlreadyResolvedException, NonConformityNotFoundException};
use Inspection\Domain\ValueObject\NonConformityStatus;
use Inspection\Presentation\Api\Dto\Input\NonConformity\UpdateNonConformityStatusInput;
use Inspection\Presentation\Api\Dto\Output\NonConformity\NonConformityOutput;
use Inspection\Presentation\Api\Trait\Inspection\InspectionExceptionUnwrapperTrait;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Domain\Exception\OrganizationAccessDeniedException;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{JsonResponse, Response as HttpResponse};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, ConflictHttpException, NotFoundHttpException};

use function is_string;

/** @implements ProcessorInterface<UpdateNonConformityStatusInput, NonConformityOutput> */
final readonly class UpdateNonConformityStatusProcessor implements ProcessorInterface
{
  use InspectionExceptionUnwrapperTrait;

  public function __construct(
    private CommandBusPort $commandBus,
    private QueryBusPort $queryBus,
    private OrganizationAuthorizationPort $authorization,
    private ApprovalGatePort $approvalGate,
    private Security $security,
  ) {
  }

  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): NonConformityOutput|HttpResponse
  {
    /** @var UpdateNonConformityStatusInput $data */
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $uriVariables['organizationId'] ?? null;
    $inspectionId = $uriVariables['inspectionId'] ?? null;
    $nonConformityId = $uriVariables['nonConformityId'] ?? null;

    if (!is_string($organizationId) || '' === $organizationId
      || !is_string($inspectionId) || '' === $inspectionId
      || !is_string($nonConformityId) || '' === $nonConformityId) {
      throw new BadRequestHttpException('OrganizationId, inspectionId and nonConformityId URI parameters are required.');
    }

    if (!$this->authorization->hasPermission($user->getId(), $organizationId, 'organization.inspection.write')) {
      throw new AccessDeniedHttpException('Missing organization.inspection.write permission.');
    }

    if (NonConformityStatus::WAIVED->value === $data->status) {
      $deferred = $this->evaluateWaiverGate($organizationId, $inspectionId, $nonConformityId, $user->getId());
      if ($deferred instanceof HttpResponse) {
        return $deferred;
      }
    }

    try {
      /** @var UpdateNonConformityStatusResult $result */
      $result = $this->commandBus->dispatch(new UpdateNonConformityStatusCommand(
        organizationId: $organizationId,
        inspectionId: $inspectionId,
        nonConformityId: $nonConformityId,
        status: $data->status,
      ));
    } catch (InspectionNotFoundException $exception) {
      throw new NotFoundHttpException($exception->getMessage(), $exception);
    } catch (NonConformityNotFoundException $exception) {
      throw new NotFoundHttpException($exception->getMessage(), $exception);
    } catch (NonConformityAlreadyResolvedException $exception) {
      throw new ConflictHttpException($exception->getMessage(), $exception);
    } catch (InvalidArgumentException $exception) {
      throw new BadRequestHttpException($exception->getMessage(), $exception);
    } catch (MessengerRuntimeException $exception) {
      $notFound = $this->findInspectionNotFoundException($exception);
      if ($notFound instanceof InspectionNotFoundException) {
        throw new NotFoundHttpException($notFound->getMessage(), $exception);
      }
      $ncNotFound = $this->findNonConformityNotFoundException($exception);
      if ($ncNotFound instanceof NonConformityNotFoundException) {
        throw new NotFoundHttpException($ncNotFound->getMessage(), $exception);
      }
      $resolved = $this->findNonConformityAlreadyResolvedException($exception);
      if ($resolved instanceof NonConformityAlreadyResolvedException) {
        throw new ConflictHttpException($resolved->getMessage(), $exception);
      }
      $invalidArgument = $this->findInvalidArgumentException($exception);
      if ($invalidArgument instanceof InvalidArgumentException) {
        throw new BadRequestHttpException($invalidArgument->getMessage(), $exception);
      }

      throw $exception;
    }

    $output = new NonConformityOutput();
    $output->id = $result->nonConformityId;
    $output->inspectionId = $result->inspectionId;
    $output->description = $result->description;
    $output->severity = $result->severity;
    $output->status = $result->status;
    $output->dueAt = $result->dueAt;
    $output->resolvedAt = $result->resolvedAt;
    $output->notes = $result->notes;
    $output->createdAt = $result->createdAt->format('c');
    $output->updatedAt = $result->updatedAt->format('c');

    return $output;
  }

  /**
   * Method evaluateWaiverGate.
   *
   * Consults the inbound `ApprovalGatePort` before waiving a non-conformity.
   * When the organization's approval policy defers this waiver, returns the
   * HTTP 202 response carrying the pending approval request summary instead
   * of dispatching the status-change command; returns `null` when the
   * action may apply immediately (the caller proceeds unchanged).
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $inspectionId the inspection identifier
   * @param string $nonConformityId the non-conformity identifier
   * @param string $actorUserId the requesting user identifier
   *
   * @return ?HttpResponse the 202 deferred response, or null to apply immediately
   */
  private function evaluateWaiverGate(string $organizationId, string $inspectionId, string $nonConformityId, string $actorUserId): ?HttpResponse
  {
    try {
      /** @var GetNonConformityResult $current */
      $current = $this->queryBus->ask(new GetNonConformityQuery(
        organizationId: $organizationId,
        inspectionId: $inspectionId,
        nonConformityId: $nonConformityId,
      ));
    } catch (InspectionNotFoundException $exception) {
      throw new NotFoundHttpException($exception->getMessage(), $exception);
    } catch (NonConformityNotFoundException $exception) {
      throw new NotFoundHttpException($exception->getMessage(), $exception);
    } catch (MessengerRuntimeException $exception) {
      $notFound = $this->findInspectionNotFoundException($exception) ?? $this->findNonConformityNotFoundException($exception);
      if (null !== $notFound) {
        throw new NotFoundHttpException($notFound->getMessage(), $exception);
      }

      throw $exception;
    }

    try {
      $decision = $this->approvalGate->evaluate(new ApprovalGateRequest(
        organizationId: $organizationId,
        actionType: ApprovalActionTypes::NC_WAIVER,
        subjectId: $nonConformityId,
        requestedByUserId: $actorUserId,
        payload: [
          'organizationId' => $organizationId,
          'inspectionId' => $inspectionId,
          'nonConformityId' => $nonConformityId,
          'severity' => $current->severity,
          'status' => NonConformityStatus::WAIVED->value,
        ],
      ));
    } catch (OrganizationAccessDeniedException $exception) {
      // Opening the approval request is a second, distinct entitlement:
      // `organization.approvals.request`, asserted inside the gate. A caller
      // who may write inspections but may not request approvals is denied,
      // not failed — mirrors `ApprovalExceptionMapperTrait`, which maps the
      // same exception on the Approval module's own endpoints.
      throw new AccessDeniedHttpException($exception->getMessage(), $exception);
    }

    if (!$decision->deferred) {
      return null;
    }

    return new JsonResponse([
      'status' => 'pending_approval',
      'approvalRequestId' => $decision->requestId,
      'approvalStatus' => $decision->status,
      'expiresAt' => $decision->expiresAt?->format('c'),
    ], HttpResponse::HTTP_ACCEPTED);
  }
}
