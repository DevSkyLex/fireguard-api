<?php

declare(strict_types=1);

namespace Maintenance\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use DateTimeImmutable;
use Exception;
use Maintenance\Application\UseCase\Command\Plan\ManageMaintenancePlan\{ManageMaintenancePlanCommand, ManageMaintenancePlanResult};
use Maintenance\Presentation\Api\Dto\Input\{ChangeMaintenancePlanInput, GenerateMaintenancePlanInput};
use Maintenance\Presentation\Api\Dto\Output\{GenerateMaintenancePlanOutput, MaintenancePlanEngineOutput};
use Maintenance\Presentation\Api\Factory\MaintenancePlanOutputFactory;
use Maintenance\Presentation\Api\Operation\MaintenancePlanOperations;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Application\Port\Outbound\CurrentActorPort;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};

use function is_string;
use function preg_match;

/** @implements ProcessorInterface<mixed, object|null> */
final readonly class MaintenancePlanProcessor implements ProcessorInterface
{
  public function __construct(private CommandBusPort $commands, private CurrentActorPort $actor, private MaintenancePlanOutputFactory $outputs)
  {
  }

  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?object
  {
    $actor = $this->actor->userId() ?? throw new AccessDeniedHttpException('Authentication required.');
    $organizationId = $uriVariables['organizationId'] ?? null;
    $planId = $uriVariables['id'] ?? null;
    if (!is_string($organizationId) || (null !== $planId && !is_string($planId))) {
      throw new BadRequestHttpException('Invalid maintenance route identifiers.');
    }
    $action = match ($operation->getName()) {
      MaintenancePlanOperations::CREATE => 'create',
      MaintenancePlanOperations::UPDATE => 'update',
      MaintenancePlanOperations::ARCHIVE => 'archive',
      MaintenancePlanOperations::GENERATE => 'generate',
      MaintenancePlanOperations::PREPARE => 'prepare_legacy',
      MaintenancePlanOperations::ACTIVATE => 'activate',
      default => throw new BadRequestHttpException('Unsupported maintenance operation.'),
    };
    $change = $data instanceof ChangeMaintenancePlanInput ? $data : null;
    $generate = $data instanceof GenerateMaintenancePlanInput ? $data : null;
    if (('create' === $action || 'update' === $action) && null === $change) {
      throw new BadRequestHttpException('A plan request is required.');
    }
    if ('update' === $action && (null !== $change?->equipmentId || null !== $change?->operationKind)) {
      throw new BadRequestHttpException('Equipment and operation kind are immutable; prepare a separate plan.');
    }
    /** @var ManageMaintenancePlanResult $result */
    $result = $this->commands->dispatch(new ManageMaintenancePlanCommand(
      $action,
      $organizationId,
      $actor,
      planId: $planId,
      equipmentId: $change?->equipmentId,
      name: null !== $change ? $change->name : (null !== $generate ? $generate->name : null),
      operationKind: $change?->operationKind,
      interval: $change?->interval,
      anchorAt: $this->date($change?->anchorAt),
      nextDueAt: $this->date($change?->nextDueAt),
      active: $change?->active,
      retry: null !== $generate ? $generate->retry : false,
      anchorOn: $change?->anchorOn,
      nextDueOn: $change?->nextDueOn,
    ));
    if ('archive' === $action) {
      return null;
    }
    if ('prepare_legacy' === $action || 'activate' === $action) {
      $output = new MaintenancePlanEngineOutput();
      $output->mode = $result->mode;
      $output->preparedCount = $result->preparedCount;

      return $output;
    }
    if ('generate' === $action) {
      $output = new GenerateMaintenancePlanOutput();
      $output->occurrenceId = $result->occurrenceId ?? '';
      $output->interventionId = $result->interventionId ?? '';
      $output->number = $result->number ?? 0;
      $output->workItemsCount = $result->workItemsCount;
      $output->replayed = $result->replayed;

      return $output;
    }

    return null === $result->details ? null : $this->outputs->fromDetails($result->details);
  }

  private function date(?string $value): ?DateTimeImmutable
  {
    if (null === $value) {
      return null;
    }
    if (1 !== preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/D', $value)) {
      throw new BadRequestHttpException('Calendar dates must be ISO date-times with an explicit offset.');
    }

    try {
      $date = new DateTimeImmutable($value);
    } catch (Exception $exception) {
      throw new BadRequestHttpException('Invalid calendar date.', $exception);
    }
    if (false !== DateTimeImmutable::getLastErrors()) {
      throw new BadRequestHttpException('Invalid calendar date.');
    }

    return $date;
  }
}
