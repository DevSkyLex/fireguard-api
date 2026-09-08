<?php

declare(strict_types=1);

namespace Equipment\Presentation\Api\Processor\Equipment;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Approval\Application\Contract\Action\ApprovalActionTypes;
use Approval\Application\Contract\Gate\{ApprovalGateDecision, ApprovalGateRequest};
use Approval\Application\Port\Inbound\ApprovalGatePort;
use Auth\Infrastructure\Security\User\SecurityUser;
use Equipment\Application\UseCase\Command\Equipment\DecommissionEquipment\{DecommissionEquipmentCommand, DecommissionEquipmentResult};
use Equipment\Domain\Exception\{EquipmentAlreadyDecommissionedException, EquipmentNotFoundException};
use Equipment\Presentation\Api\Dto\Output\Equipment\EquipmentOutput;
use Equipment\Presentation\Api\Factory\EquipmentOutputFactory;
use Equipment\Presentation\Api\Trait\Equipment\EquipmentExceptionUnwrapperTrait;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{JsonResponse, Response as HttpResponse};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, ConflictHttpException, NotFoundHttpException};

use function is_string;

/**
 * Processor DecommissionEquipmentProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<mixed, EquipmentOutput>
 */
final readonly class DecommissionEquipmentProcessor implements ProcessorInterface
{
  use EquipmentExceptionUnwrapperTrait;

  // #region Constructor
  public function __construct(
    private CommandBusPort $commandBus,
    private OrganizationAuthorizationPort $authorization,
    private ApprovalGatePort $approvalGate,
    private Security $security,
    private EquipmentOutputFactory $outputFactory,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method process.
   *
   * @since 1.0.0
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): EquipmentOutput|HttpResponse
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $uriVariables['organizationId'] ?? null;
    $equipmentId = $uriVariables['equipmentId'] ?? null;

    if (!is_string($organizationId) || '' === $organizationId || !is_string($equipmentId) || '' === $equipmentId) {
      throw new BadRequestHttpException('OrganizationId and equipmentId URI parameters are required.');
    }

    $decision = $this->authorization->resolveAccess($user->getId(), $organizationId, 'organization.equipment.write');
    if ($decision->isOutsideScope()) {
      throw new NotFoundHttpException('Organization not found.');
    }
    if (!$decision->isGranted()) {
      throw new AccessDeniedHttpException('Missing organization.equipment.write permission.');
    }

    $decision = $this->approvalGate->evaluate(new ApprovalGateRequest(
      organizationId: $organizationId,
      actionType: ApprovalActionTypes::EQUIPMENT_DECOMMISSION,
      subjectId: $equipmentId,
      requestedByUserId: $user->getId(),
      payload: [
        'organizationId' => $organizationId,
        'equipmentId' => $equipmentId,
      ],
    ));

    if ($decision->deferred) {
      return self::deferredResponse($decision);
    }

    try {
      /** @var DecommissionEquipmentResult $result */
      $result = $this->commandBus->dispatch(new DecommissionEquipmentCommand(
        organizationId: $organizationId,
        equipmentId: $equipmentId,
      ));
    } catch (EquipmentNotFoundException $exception) {
      throw new NotFoundHttpException($exception->getMessage(), $exception);
    } catch (EquipmentAlreadyDecommissionedException $exception) {
      throw new ConflictHttpException($exception->getMessage(), $exception);
    } catch (InvalidArgumentException $exception) {
      throw new BadRequestHttpException($exception->getMessage(), $exception);
    } catch (MessengerRuntimeException $exception) {
      $notFound = $this->findEquipmentNotFoundException($exception);
      if ($notFound instanceof EquipmentNotFoundException) {
        throw new NotFoundHttpException($notFound->getMessage(), $exception);
      }

      $decommissioned = $this->findEquipmentAlreadyDecommissionedException($exception);
      if ($decommissioned instanceof EquipmentAlreadyDecommissionedException) {
        throw new ConflictHttpException($decommissioned->getMessage(), $exception);
      }

      $invalidArgument = $this->findInvalidArgumentException($exception);
      if ($invalidArgument instanceof InvalidArgumentException) {
        throw new BadRequestHttpException($invalidArgument->getMessage(), $exception);
      }

      throw $exception;
    }

    return $this->outputFactory->fromView($result);
  }

  /**
   * Method deferredResponse.
   *
   * @static
   *
   * Builds the HTTP 202 response carrying the pending approval request
   * summary, returned instead of decommissioning the equipment when the
   * organization's approval policy defers this action.
   *
   * @since 1.0.0
   *
   * @param ApprovalGateDecision $decision the deferred gate decision
   *
   * @return JsonResponse the 202 deferred response
   */
  private static function deferredResponse(ApprovalGateDecision $decision): JsonResponse
  {
    return new JsonResponse([
      'status' => 'pending_approval',
      'approvalRequestId' => $decision->requestId,
      'approvalStatus' => $decision->status,
      'expiresAt' => $decision->expiresAt?->format('c'),
    ], HttpResponse::HTTP_ACCEPTED);
  }
  // #endregion
}
