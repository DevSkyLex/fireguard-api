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
      if (!$data instanceof CreateServiceRequestInput) {
        throw ServiceRequestException::invalid('Invalid repair request input.');
      }
      /** @var CreateServiceRequestResult $result */
      $result = $this->commands->dispatch(new CreateServiceRequestCommand($actorId, $organizationId, $data->equipmentId, $data->siteId, $data->title, $data->description, $data->priority, $data->originInspectionId, $data->originNonConformityId));

      return ServiceRequestOutput::fromView($result->request);
    }
    $requestId = $this->id($uriVariables, 'id');
    $revision = $this->revision();
    if (ServiceRequestOperations::CONVERT === $operation->getName()) {
      if (!$data instanceof ConvertServiceRequestInput) {
        throw ServiceRequestException::invalid('Invalid repair conversion input.');
      }
      /** @var ConvertServiceRequestResult $result */
      $result = $this->commands->dispatch(new ConvertServiceRequestCommand($actorId, $organizationId, $requestId, $revision, $data->clientOperationId, $data->existingInterventionId, $data->existingTaskId));

      return ServiceRequestOutput::fromView($result->request);
    }
    $action = match ($operation->getName()) {
      ServiceRequestOperations::QUALIFY => 'qualify', ServiceRequestOperations::REJECT => 'reject', ServiceRequestOperations::CANCEL => 'cancel', default => 'patch'
    };
    $changes = [];
    $note = null;
    $reason = null;
    $equipmentId = null;
    if ('patch' === $action) {
      if (!$data instanceof UpdateServiceRequestInput) {
        throw ServiceRequestException::invalid('Invalid repair request patch.');
      }
      foreach ($this->requests->getCurrentRequest()?->getPayload()->all() ?? [] as $field => $value) {
        if (!property_exists($data, $field)) {
          throw ServiceRequestException::invalid('Unknown repair request field.');
        }
        $changes[$field] = $data->{$field};
      }
    } elseif ('qualify' === $action) {
      if (!$data instanceof QualifyServiceRequestInput) {
        throw ServiceRequestException::invalid('Invalid qualification input.');
      }
      $note = $data->note;
      $equipmentId = $data->equipmentId;
    } else {
      if (!$data instanceof DecisionServiceRequestInput) {
        throw ServiceRequestException::invalid('Invalid repair request decision.');
      }
      $reason = $data->reason;
    }
    /** @var ChangeServiceRequestResult $result */
    $result = $this->commands->dispatch(new ChangeServiceRequestCommand($actorId, $organizationId, $requestId, $action, $revision, $changes, $note, $reason, $equipmentId));

    return ServiceRequestOutput::fromView($result->request);
  }

  private function revision(): ?int
  {
    $header = $this->requests->getCurrentRequest()?->headers->get('If-Match');

    return null === $header ? null : (1 === preg_match('/^"revision-(\d+)"$/', $header, $matches) ? (int) $matches[1] : -1);
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
