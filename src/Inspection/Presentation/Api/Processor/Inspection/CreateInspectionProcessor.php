<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Processor\Inspection;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Inspection\Application\UseCase\Command\Inspection\CreateInspection\{CreateInspectionCommand, CreateInspectionResult};
use Inspection\Presentation\Api\Dto\Input\Inspection\CreateInspectionInput;
use Inspection\Presentation\Api\Dto\Output\Inspection\InspectionOutput;
use Inspection\Presentation\Api\Mapper\InspectionOutputMapper;
use Inspection\Presentation\Api\Trait\Inspection\InspectionExceptionUnwrapperTrait;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};

use function is_string;

/** @implements ProcessorInterface<CreateInspectionInput, InspectionOutput> */
final readonly class CreateInspectionProcessor implements ProcessorInterface
{
  use InspectionExceptionUnwrapperTrait;

  public function __construct(
    private CommandBusPort $commandBus,
    private InspectionOutputMapper $outputMapper,
    private OrganizationAuthorizationPort $authorization,
    private Security $security,
  ) {
  }

  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): InspectionOutput
  {
    /** @var CreateInspectionInput $data */
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $uriVariables['organizationId'] ?? null;
    if (!is_string($organizationId) || '' === $organizationId) {
      throw new BadRequestHttpException('OrganizationId URI parameter is required.');
    }

    if (!$this->authorization->hasPermission($user->getId(), $organizationId, 'organization.inspection.write')) {
      throw new AccessDeniedHttpException('Missing organization.inspection.write permission.');
    }

    try {
      /** @var CreateInspectionResult $result */
      $result = $this->commandBus->dispatch(new CreateInspectionCommand(
        organizationId: $organizationId,
        equipmentId: $data->equipmentId,
        result: $data->result,
        performedAt: $data->performedAt,
        inspectorType: $data->inspectorType,
        inspectorName: $data->inspectorName,
        facilityId: $data->facilityId,
        checklistId: $data->checklistId,
        inspectorUserId: $data->inspectorUserId,
        inspectorOrganizationName: $data->inspectorOrganizationName,
        notes: $data->notes,
        signature: $data->signature,
      ));
    } catch (InvalidArgumentException $exception) {
      throw new BadRequestHttpException($exception->getMessage(), $exception);
    } catch (MessengerRuntimeException $exception) {
      $invalidArgument = $this->findInvalidArgumentException($exception);
      if ($invalidArgument instanceof InvalidArgumentException) {
        throw new BadRequestHttpException($invalidArgument->getMessage(), $exception);
      }

      throw $exception;
    }

    return $this->outputMapper->fromCreateResult($result);
  }
}
