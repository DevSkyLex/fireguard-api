<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Processor\NonConformity;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Inspection\Application\UseCase\Command\NonConformity\AddNonConformity\{AddNonConformityCommand, AddNonConformityResult};
use Inspection\Domain\Exception\{InspectionAlreadyClosedException, InspectionNotFoundException};
use Inspection\Presentation\Api\Dto\Input\NonConformity\AddNonConformityInput;
use Inspection\Presentation\Api\Dto\Output\NonConformity\NonConformityOutput;
use Inspection\Presentation\Api\Trait\Inspection\InspectionExceptionUnwrapperTrait;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, ConflictHttpException, NotFoundHttpException};

use function is_string;

/** @implements ProcessorInterface<AddNonConformityInput, NonConformityOutput> */
final readonly class AddNonConformityProcessor implements ProcessorInterface
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
    /** @var AddNonConformityInput $data */
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $uriVariables['organizationId'] ?? null;
    $inspectionId = $uriVariables['inspectionId'] ?? null;

    if (!is_string($organizationId) || '' === $organizationId || !is_string($inspectionId) || '' === $inspectionId) {
      throw new BadRequestHttpException('OrganizationId and inspectionId URI parameters are required.');
    }

    if (!$this->authorization->hasPermission($user->getId(), $organizationId, 'organization.inspection.write')) {
      throw new AccessDeniedHttpException('Missing organization.inspection.write permission.');
    }

    try {
      /** @var AddNonConformityResult $result */
      $result = $this->commandBus->dispatch(new AddNonConformityCommand(
        organizationId: $organizationId,
        inspectionId: $inspectionId,
        description: $data->description,
        severity: $data->severity,
        dueAt: $data->dueAt,
        notes: $data->notes,
      ));
    } catch (InspectionNotFoundException $exception) {
      throw new NotFoundHttpException($exception->getMessage(), $exception);
    } catch (InspectionAlreadyClosedException $exception) {
      throw new ConflictHttpException($exception->getMessage(), $exception);
    } catch (InvalidArgumentException $exception) {
      throw new BadRequestHttpException($exception->getMessage(), $exception);
    } catch (MessengerRuntimeException $exception) {
      $notFound = $this->findInspectionNotFoundException($exception);
      if ($notFound instanceof InspectionNotFoundException) {
        throw new NotFoundHttpException($notFound->getMessage(), $exception);
      }
      $closed = $this->findInspectionAlreadyClosedException($exception);
      if ($closed instanceof InspectionAlreadyClosedException) {
        throw new ConflictHttpException($closed->getMessage(), $exception);
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
    $output->notes = $result->notes;
    $output->createdAt = $result->createdAt->format('c');
    $output->updatedAt = $result->updatedAt->format('c');

    return $output;
  }
}
