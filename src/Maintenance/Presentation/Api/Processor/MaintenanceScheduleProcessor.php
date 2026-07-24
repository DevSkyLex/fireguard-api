<?php

declare(strict_types=1);

namespace Maintenance\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Maintenance\Application\UseCase\Command\Schedule\SetMaintenanceScheduleOverride\{SetMaintenanceScheduleOverrideCommand, SetMaintenanceScheduleOverrideResult};
use Maintenance\Presentation\Api\Dto\Input\UpdateMaintenanceScheduleInput;
use Maintenance\Presentation\Api\Dto\Output\MaintenanceScheduleOutput;
use Maintenance\Presentation\Api\Factory\MaintenanceScheduleOutputFactory;
use Maintenance\Presentation\Api\Trait\MaintenanceExceptionMapperTrait;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};
use Throwable;

use function is_string;

/**
 * Processor MaintenanceScheduleProcessor.
 *
 * Handles `PATCH /api/maintenance/schedules/{id}` — the only writable field
 * is `intervalOverride` (a present `null` clears it).
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<UpdateMaintenanceScheduleInput, MaintenanceScheduleOutput>
 */
final readonly class MaintenanceScheduleProcessor implements ProcessorInterface
{
  use MaintenanceExceptionMapperTrait;

  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus the command bus value
   * @param MaintenanceScheduleOutputFactory $mapper the mapper value
   * @param Security $security the security value
   */
  public function __construct(
    private CommandBusPort $commandBus,
    private MaintenanceScheduleOutputFactory $mapper,
    private Security $security,
  ) {
  }

  /**
   * Method process.
   *
   * @since 1.0.0
   *
   * @param mixed $data the data value
   * @param Operation $operation the operation value
   * @param array<string, mixed> $uriVariables the uri variables value
   * @param array<string, mixed> $context the context value
   *
   * @return MaintenanceScheduleOutput the process result
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MaintenanceScheduleOutput
  {
    $user = $this->user();
    $id = is_string($uriVariables['id'] ?? null) ? $uriVariables['id'] : null;
    if (null === $id) {
      throw new BadRequestHttpException('The id URI parameter is required.');
    }

    if (!$data instanceof UpdateMaintenanceScheduleInput) {
      throw new BadRequestHttpException('Invalid request body.');
    }

    try {
      /** @var SetMaintenanceScheduleOverrideResult $result */
      $result = $this->commandBus->dispatch(new SetMaintenanceScheduleOverrideCommand(
        userId: $user->getId(),
        scheduleId: $id,
        intervalOverride: $data->intervalOverride,
      ));
    } catch (Throwable $exception) {
      throw $this->mapMaintenanceException($exception);
    }

    return $this->mapper->fromView($result->schedule);
  }

  /**
   * Method user.
   *
   * @since 1.0.0
   *
   * @return SecurityUser the user result
   */
  private function user(): SecurityUser
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    return $user;
  }
}
