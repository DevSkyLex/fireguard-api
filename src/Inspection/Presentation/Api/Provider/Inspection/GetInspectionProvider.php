<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Provider\Inspection;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Inspection\Application\UseCase\Query\Inspection\GetInspection\{GetInspectionQuery, GetInspectionResult};
use Inspection\Domain\Exception\InspectionNotFoundException;
use Inspection\Presentation\Api\Dto\Output\Inspection\InspectionOutput;
use Inspection\Presentation\Api\Trait\Inspection\InspectionExceptionUnwrapperTrait;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};

use function is_string;

/** @implements ProviderInterface<InspectionOutput> */
final readonly class GetInspectionProvider implements ProviderInterface
{
  use InspectionExceptionUnwrapperTrait;

  public function __construct(
    private QueryBusPort $queryBus,
    private OrganizationAuthorizationPort $authorization,
    private Security $security,
  ) {
  }

  public function provide(Operation $operation, array $uriVariables = [], array $context = []): InspectionOutput
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $uriVariables['organizationId'] ?? null;
    $inspectionId = $uriVariables['inspectionId'] ?? null;

    if (!is_string($organizationId) || '' === $organizationId || !is_string($inspectionId) || '' === $inspectionId) {
      throw new BadRequestHttpException('OrganizationId and inspectionId URI parameters are required.');
    }

    if (!$this->authorization->hasPermission($user->getId(), $organizationId, 'organization.inspection.read')) {
      throw new AccessDeniedHttpException('Missing organization.inspection.read permission.');
    }

    try {
      /** @var GetInspectionResult $result */
      $result = $this->queryBus->ask(new GetInspectionQuery(
        organizationId: $organizationId,
        inspectionId: $inspectionId,
      ));
    } catch (InspectionNotFoundException $exception) {
      throw new NotFoundHttpException($exception->getMessage(), $exception);
    } catch (InvalidArgumentException $exception) {
      throw new BadRequestHttpException($exception->getMessage(), $exception);
    } catch (MessengerRuntimeException $exception) {
      $notFound = $this->findInspectionNotFoundException($exception);
      if ($notFound instanceof InspectionNotFoundException) {
        throw new NotFoundHttpException($notFound->getMessage(), $exception);
      }
      $invalidArgument = $this->findInvalidArgumentException($exception);
      if ($invalidArgument instanceof InvalidArgumentException) {
        throw new BadRequestHttpException($invalidArgument->getMessage(), $exception);
      }

      throw $exception;
    }

    return $this->mapResult($result);
  }

  private function mapResult(GetInspectionResult $result): InspectionOutput
  {
    $output = new InspectionOutput();
    $output->id = $result->inspectionId;
    $output->organizationId = $result->organizationId;
    $output->equipmentId = $result->equipmentId;
    $output->facilityId = $result->facilityId;
    $output->result = $result->result;
    $output->status = $result->status;
    $output->performedAt = $result->performedAt;
    $output->inspectorType = $result->inspectorType;
    $output->inspectorName = $result->inspectorName;
    $output->inspectorUserId = $result->inspectorUserId;
    $output->inspectorOrganizationName = $result->inspectorOrganizationName;
    $output->checklistId = $result->checklistId;
    $output->notes = $result->notes;
    $output->signature = $result->signature;
    $output->nonConformitiesCount = $result->nonConformitiesCount;
    $output->createdAt = $result->createdAt->format('c');
    $output->updatedAt = $result->updatedAt->format('c');

    return $output;
  }
}
