<?php

declare(strict_types=1);

namespace Workload\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};
use Workload\Application\UseCase\Command\Capacity\ChangeCapacity\{ChangeCapacityCommand, ChangeCapacityResult};
use Workload\Presentation\Api\Dto\Input\{CapacityExceptionInput, CapacityWeekInput};
use Workload\Presentation\Api\Dto\Output\CapacityChangeOutput;

use function is_string;

/**
 * CapacityProcessor.
 *
 * @category Workload
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<mixed, CapacityChangeOutput|null>
 */
final readonly class CapacityProcessor implements ProcessorInterface
{
  /**
   * @since 1.0.0
   *
   * @param CommandBusPort $commands dispatches write use cases through the command bus
   * @param Security $security resolves the authenticated account at the HTTP boundary
   */
  public function __construct(private CommandBusPort $commands, private Security $security)
  {
  }

  /**
   * Translates a capacity request into a command, leaving validation and authorization to the handler.
   *
   * @since 1.0.0
   *
   * @param mixed $data deserialized API input; authorization remains in the use case
   * @param Operation $operation API operation metadata
   * @param array<string, mixed> $uriVariables
   * @param array<string, mixed> $context
   *
   * @return ?CapacityChangeOutput persisted capacity change, or null for cancellation
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?CapacityChangeOutput
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }
    $organizationId = $uriVariables['organizationId'] ?? null;
    if (!is_string($organizationId)) {
      throw new BadRequestHttpException('Organization is required.');
    }
    $kind = $operation instanceof \ApiPlatform\Metadata\Delete ? 'cancel_exception' : ($data instanceof CapacityWeekInput ? 'week' : 'exception');
    if ('cancel_exception' !== $kind && !$data instanceof CapacityWeekInput && !$data instanceof CapacityExceptionInput) {
      throw new BadRequestHttpException('Capacity input is required.');
    }
    /** @var ChangeCapacityResult $result */
    $result = $this->commands->dispatch(new ChangeCapacityCommand(
      $user->getId(),
      $organizationId,
      $kind,
      is_string($uriVariables['memberId'] ?? null) ? $uriVariables['memberId'] : null,
      $data instanceof CapacityWeekInput ? $data->effectiveOn : null,
      $data instanceof CapacityWeekInput ? $data->minutes : [],
      $data instanceof CapacityExceptionInput ? $data->startsOn : null,
      $data instanceof CapacityExceptionInput ? $data->endsOn : null,
      $data instanceof CapacityExceptionInput ? $data->minutes : 0,
      is_string($uriVariables['exceptionId'] ?? null) ? $uriVariables['exceptionId'] : null,
    ));

    return 'cancel_exception' === $kind ? null : new CapacityChangeOutput($result->id);
  }
}
