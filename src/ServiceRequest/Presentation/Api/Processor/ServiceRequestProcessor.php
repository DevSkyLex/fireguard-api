<?php

declare(strict_types=1);

namespace ServiceRequest\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ServiceRequest\Application\UseCase\Command\ChangeServiceRequest\{ChangeServiceRequestCommand, ChangeServiceRequestResult};
use ServiceRequest\Application\UseCase\Command\ConvertServiceRequest\{ConvertServiceRequestCommand, ConvertServiceRequestResult};
use ServiceRequest\Application\UseCase\Command\CreateServiceRequest\{CreateServiceRequestCommand, CreateServiceRequestResult};
use ServiceRequest\Domain\Exception\ServiceRequestException;
use ServiceRequest\Presentation\Api\Dto\Input\{ConvertServiceRequestInput, CreateServiceRequestInput, DecisionServiceRequestInput, QualifyServiceRequestInput, UpdateServiceRequestInput};
use ServiceRequest\Presentation\Api\Dto\Output\ServiceRequestOutput;
use ServiceRequest\Presentation\Api\Operation\ServiceRequestOperations;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Application\Port\Outbound\CurrentActorPort;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

use function is_string;
use function preg_match;
use function property_exists;

/**
 * Class ServiceRequestProcessor
 *
 * Translates explicit workflow actions into application commands.
 *
 * @category Processor
 *
 * @implements ProcessorInterface<CreateServiceRequestInput|UpdateServiceRequestInput|QualifyServiceRequestInput|DecisionServiceRequestInput|ConvertServiceRequestInput,ServiceRequestOutput>
 */
final readonly class ServiceRequestProcessor implements ProcessorInterface
{
  public function __construct(private CommandBusPort $commands, private CurrentActorPort $actor, private RequestStack $requests)
  {
  }

  /**
   * @param array<string,mixed> $uriVariables
   * @param array<string,mixed> $context
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ServiceRequestOutput
  {
    $actorId = $this->actor->userId() ?? throw new AccessDeniedHttpException('Authentication required.');
    $organizationId = $this->id($uriVariables, 'organizationId');
    if (ServiceRequestOperations::CREATE === $operation->getName()) {
      return $this->create($data, $actorId, $organizationId);
    }
    $requestId = $this->id($uriVariables, 'id');
    $revision = $this->revision();
    if (ServiceRequestOperations::CONVERT === $operation->getName()) {
      return $this->convert($data, $actorId, $organizationId, $requestId, $revision);
    }
    $action = match ($operation->getName()) {
      ServiceRequestOperations::QUALIFY => 'qualify', ServiceRequestOperations::REJECT => 'reject', ServiceRequestOperations::CANCEL => 'cancel', default => 'patch'
    };
    /** @var ChangeServiceRequestResult $result */
    $result = $this->commands->dispatch($this->change($data, $action, $actorId, $organizationId, $requestId, $revision));

    return ServiceRequestOutput::fromView($result->request);
  }

  /**
   * Method create
   *
   * @access private
   *
   * @param mixed $data declared creation input
   * @param string $actorId authenticated actor
   * @param string $organizationId route scope
   *
   * @return ServiceRequestOutput created request metadata
   */
  private function create(mixed $data, string $actorId, string $organizationId): ServiceRequestOutput
  {
    if (!$data instanceof CreateServiceRequestInput) {
      throw ServiceRequestException::invalid('Invalid repair request input.');
    }
    /** @var CreateServiceRequestResult $result */
    $result = $this->commands->dispatch(new CreateServiceRequestCommand($actorId, $organizationId, $data->equipmentId, $data->siteId, $data->title, $data->description, $data->priority, $data->originInspectionId, $data->originNonConformityId));

    return ServiceRequestOutput::fromView($result->request);
  }

  /**
   * Method convert
   *
   * @access private
   *
   * @param mixed $data explicit conversion input
   * @param string $actorId authenticated actor
   * @param string $organizationId route scope
   * @param string $requestId route request identity
   * @param int|null $revision parsed optimistic precondition
   *
   * @return ServiceRequestOutput converted or replayed request metadata
   */
  private function convert(mixed $data, string $actorId, string $organizationId, string $requestId, ?int $revision): ServiceRequestOutput
  {
    if (!$data instanceof ConvertServiceRequestInput) {
      throw ServiceRequestException::invalid('Invalid repair conversion input.');
    }
    /** @var ConvertServiceRequestResult $result */
    $result = $this->commands->dispatch(new ConvertServiceRequestCommand($actorId, $organizationId, $requestId, $revision, $data->clientOperationId, $data->existingInterventionId, $data->existingTaskId));

    return ServiceRequestOutput::fromView($result->request);
  }

  /**
   * Method change
   *
   * Translates lifecycle intent without applying domain policy or filling omitted patch fields.
   *
   * @access private
   *
   * @param mixed $data patch, qualification or decision input
   * @param string $action explicit lifecycle operation
   * @param string $actorId authenticated actor
   * @param string $organizationId route scope
   * @param string $requestId route request identity
   * @param int|null $revision parsed optimistic precondition
   *
   * @return ChangeServiceRequestCommand exact declared fields and action
   */
  private function change(mixed $data, string $action, string $actorId, string $organizationId, string $requestId, ?int $revision): ChangeServiceRequestCommand
  {
    if ('patch' === $action) {
      return new ChangeServiceRequestCommand($actorId, $organizationId, $requestId, $action, $revision, $this->patch($data));
    }
    if ('qualify' === $action) {
      if (!$data instanceof QualifyServiceRequestInput) {
        throw ServiceRequestException::invalid('Invalid qualification input.');
      }

      return new ChangeServiceRequestCommand($actorId, $organizationId, $requestId, $action, $revision, note:$data->note, equipmentId:$data->equipmentId);
    }
    if (!$data instanceof DecisionServiceRequestInput) {
      throw ServiceRequestException::invalid('Invalid repair request decision.');
    }

    return new ChangeServiceRequestCommand($actorId, $organizationId, $requestId, $action, $revision, reason:$data->reason);
  }

  /**
   * Method patch
   *
   * The request body alone selects changed fields, preserving omitted versus explicit null values.
   *
   * @access private
   *
   * @param mixed $data deserialized patch input
   *
   * @return array<string,mixed> supplied patch fields
   */
  private function patch(mixed $data): array
  {
    if (!$data instanceof UpdateServiceRequestInput) {
      throw ServiceRequestException::invalid('Invalid repair request patch.');
    }
    $changes = [];
    foreach ($this->requests->getCurrentRequest()?->getPayload()->all() ?? [] as $field => $value) {
      if (!property_exists($data, $field)) {
        throw ServiceRequestException::invalid('Unknown repair request field.');
      }
      $changes[$field] = $data->{$field};
    }

    return $changes;
  }

  private function revision(): ?int
  {
    $header = $this->requests->getCurrentRequest()?->headers->get('If-Match');
    if (null === $header) {
      return null;
    }

    return 1 === preg_match('/^"revision-(\d+)"$/', $header, $matches) ? (int) $matches[1] : -1;
  }

  /**
   * @param array<string,mixed> $variables
   */
  private function id(array $variables, string $key): string
  {
    $value = $variables[$key] ?? null;

    return is_string($value) ? $value : '';
  }
}
