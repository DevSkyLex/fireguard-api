<?php

declare(strict_types=1);

namespace Customer\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Customer\Application\UseCase\Command\ChangeCustomer\{ChangeCustomerCommand, ChangeCustomerResult};
use Customer\Application\UseCase\Command\CreateCustomer\{CreateCustomerCommand, CreateCustomerResult};
use Customer\Domain\Exception\CustomerException;
use Customer\Presentation\Api\Dto\Input\{ChangeCustomerInput, CreateCustomerInput};
use Customer\Presentation\Api\Dto\Output\CustomerOutput;
use Customer\Presentation\Api\Operation\CustomerOperations;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Application\Port\Outbound\CurrentActorPort;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

use function is_string;
use function preg_match;
use function property_exists;

/**
 * Class CustomerProcessor
 *
 * Translates validated transport fields without business decisions.
 *
 * @category Processor
 *
 * @implements ProcessorInterface<CreateCustomerInput|ChangeCustomerInput|null,CustomerOutput>
 */
final readonly class CustomerProcessor implements ProcessorInterface
{
  public function __construct(private CommandBusPort $commands, private CurrentActorPort $actor, private RequestStack $requests)
  {
  }

  /**
   * @param array<string,mixed> $uriVariables @param array<string,mixed> $context
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CustomerOutput
  {
    $actorId = $this->actor->userId() ?? throw new AccessDeniedHttpException('Authentication required.');
    $organizationId = $this->identifier($uriVariables, 'organizationId');
    if (CustomerOperations::CREATE === $operation->getName()) {
      if (!$data instanceof CreateCustomerInput) {
        throw CustomerException::invalid('Invalid customer input.');
      }
      /** @var CreateCustomerResult $result */
      $result = $this->commands->dispatch(new CreateCustomerCommand($actorId, $organizationId, $data->name, $data->code, $data->email, $data->phone, $data->contacts));

      return CustomerOutput::fromView($result->customer);
    }
    $action = match ($operation->getName()) {
      CustomerOperations::ARCHIVE => 'archive', CustomerOperations::RESTORE => 'restore', default => 'patch'
    };
    $changes = [];
    if ('patch' === $action) {
      if (!$data instanceof ChangeCustomerInput) {
        throw CustomerException::invalid('Invalid customer input.');
      }
      foreach ($this->requests->getCurrentRequest()?->getPayload()->all() ?? [] as $field => $value) {
        if (!property_exists($data, $field)) {
          throw CustomerException::invalid('Unknown customer field.');
        }
        $changes[$field] = $data->{$field};
      }
    }
    $header = $this->requests->getCurrentRequest()?->headers->get('If-Match');
    $revision = null === $header ? null : (1 === preg_match('/^"revision-(\d+)"$/', $header, $matches) ? (int) $matches[1] : -1);
    /** @var ChangeCustomerResult $result */
    $result = $this->commands->dispatch(new ChangeCustomerCommand($actorId, $organizationId, $this->identifier($uriVariables, 'id'), $action, $revision, $changes));

    return CustomerOutput::fromView($result->customer);
  }

  /**
   * @param array<string,mixed> $variables
   */
  private function identifier(array $variables, string $key): string
  {
    $value = $variables[$key] ?? null;

    return is_string($value) ? $value : '';
  }
}
