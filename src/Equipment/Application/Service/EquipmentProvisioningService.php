<?php

declare(strict_types=1);

namespace Equipment\Application\Service;

use Equipment\Application\Contract\Provisioning\{ProvisionEquipmentRequest, ProvisionEquipmentResult, ProvisionOutcome};
use Equipment\Application\Port\Inbound\EquipmentProvisioningPort;
use Equipment\Application\UseCase\Command\Equipment\CreateEquipment\{CreateEquipmentCommand, CreateEquipmentResult};
use Equipment\Domain\Exception\EquipmentSerialNumberAlreadyExistsException;
use InvalidArgumentException;
use Organization\Application\Contract\Quota\OrganizationQuotaExceededException;
use Shared\Application\Exception\{MessengerExceptionUnwrapperTrait, MessengerRuntimeException};
use Shared\Application\Port\Inbound\CommandBusPort;

/**
 * Service EquipmentProvisioningService.
 *
 * Implements {@see EquipmentProvisioningPort} by dispatching the existing
 * `CreateEquipmentCommand` through the command bus — the same synchronous
 * path the HTTP API uses, so the transactional quota check
 * (`OrganizationQuotaPort::assertCanAdd()`) runs intact. Every failure the
 * command bus can raise for this command is translated into a typed
 * {@see ProvisionEquipmentResult} outcome rather than rethrown, unwrapping
 * `MessengerRuntimeException` exactly like
 * `Equipment\Presentation\Api\Processor\Equipment\CreateEquipmentProcessor`
 * does — callers depend on this port and its contracts, never on
 * `CreateEquipmentCommand` or any Equipment Domain type directly.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class EquipmentProvisioningService implements EquipmentProvisioningPort
{
  use MessengerExceptionUnwrapperTrait;

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus the command bus value
   */
  public function __construct(
    private CommandBusPort $commandBus,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method provision.
   *
   * @since 1.0.0
   *
   * @param ProvisionEquipmentRequest $request the provisioning request
   *
   * @return ProvisionEquipmentResult the provisioning outcome
   */
  public function provision(ProvisionEquipmentRequest $request): ProvisionEquipmentResult
  {
    try {
      /** @var CreateEquipmentResult $result */
      $result = $this->commandBus->dispatch(new CreateEquipmentCommand(
        organizationId: $request->organizationId,
        type: $request->type,
        subType: $request->subType,
        brand: $request->brand,
        model: $request->model,
        serialNumber: $request->serialNumber,
        locationLabel: $request->locationLabel,
        dryRun: $request->dryRun,
        quotaProjectionOffset: $request->quotaProjectionOffset,
      ));
    } catch (OrganizationQuotaExceededException $exception) {
      return new ProvisionEquipmentResult(ProvisionOutcome::QUOTA_EXCEEDED, message: $exception->getMessage());
    } catch (EquipmentSerialNumberAlreadyExistsException|InvalidArgumentException $exception) {
      return new ProvisionEquipmentResult(ProvisionOutcome::INVALID, message: $exception->getMessage());
    } catch (MessengerRuntimeException $exception) {
      return $this->fromWrappedException($exception);
    }

    return new ProvisionEquipmentResult(ProvisionOutcome::CREATED, resourceId: $result->equipmentId);
  }

  /**
   * Method fromWrappedException.
   *
   * Unwraps a command-bus-wrapped failure into its provisioning outcome.
   *
   * @since 1.0.0
   *
   * @param MessengerRuntimeException $exception the wrapped exception
   *
   * @return ProvisionEquipmentResult the provisioning outcome
   */
  private function fromWrappedException(MessengerRuntimeException $exception): ProvisionEquipmentResult
  {
    $quota = $this->findException($exception, OrganizationQuotaExceededException::class);
    if ($quota instanceof OrganizationQuotaExceededException) {
      return new ProvisionEquipmentResult(ProvisionOutcome::QUOTA_EXCEEDED, message: $quota->getMessage());
    }

    $serial = $this->findException($exception, EquipmentSerialNumberAlreadyExistsException::class);
    if ($serial instanceof EquipmentSerialNumberAlreadyExistsException) {
      return new ProvisionEquipmentResult(ProvisionOutcome::INVALID, message: $serial->getMessage());
    }

    $invalid = $this->findException($exception, InvalidArgumentException::class);
    if ($invalid instanceof InvalidArgumentException) {
      return new ProvisionEquipmentResult(ProvisionOutcome::INVALID, message: $invalid->getMessage());
    }

    throw $exception;
  }
  // #endregion
}
