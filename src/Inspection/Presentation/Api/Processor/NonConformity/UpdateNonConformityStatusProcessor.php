<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Processor\NonConformity;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Inspection\Application\UseCase\Command\NonConformity\UpdateNonConformityStatus\{UpdateNonConformityStatusCommand, UpdateNonConformityStatusResult};
use Inspection\Domain\Exception\{InspectionNotFoundException, NonConformityAlreadyResolvedException, NonConformityNotFoundException};
use Inspection\Presentation\Api\Dto\Input\NonConformity\UpdateNonConformityStatusInput;
use Inspection\Presentation\Api\Dto\Output\NonConformity\NonConformityOutput;
use Inspection\Presentation\Api\Trait\Inspection\InspectionExceptionUnwrapperTrait;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, ConflictHttpException, NotFoundHttpException};

use function is_string;

/** @implements ProcessorInterface<UpdateNonConformityStatusInput, NonConformityOutput> */
final readonly class UpdateNonConformityStatusProcessor implements ProcessorInterface
{
  use InspectionExceptionUnwrapperTrait;

  public function __construct(
    private CommandBusPort $commandBus,
    private OrganizationAuthorizationPort $authorization,
    private Security $security,
  ) {
  }

  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): NonConformityOutput
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
}
