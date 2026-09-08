<?php

declare(strict_types=1);

namespace Equipment\Application\Service;

use Equipment\Application\Contract\Provisioning\{ProvisionEquipmentRequest, ProvisionEquipmentResult, ProvisionOutcome};
use Equipment\Application\Port\Inbound\EquipmentProvisioningPort;
use Equipment\Application\Port\Outbound\FacilityValidationPort;
use Equipment\Application\UseCase\Command\Equipment\AssignToFacility\AssignToFacilityCommand;
use Equipment\Application\UseCase\Command\Equipment\CreateEquipment\{CreateEquipmentCommand, CreateEquipmentResult};
use Equipment\Domain\Exception\EquipmentSerialNumberAlreadyExistsException;
use InvalidArgumentException;
use Organization\Application\Contract\Quota\OrganizationQuotaExceededException;
use Shared\Application\Exception\{MessengerExceptionUnwrapperTrait, MessengerRuntimeException};
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Domain\Exception\InvalidValueException;
use Throwable;

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
   * @param FacilityValidationPort $facilityValidation the facility validation/lookup port
   */
  public function __construct(
    private CommandBusPort $commandBus,
    private FacilityValidationPort $facilityValidation,
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
    $facilityId = null;

    if (null !== $request->facilityCode && '' !== $request->facilityCode) {
      $facilityId = $this->facilityValidation->resolveIdByCode($request->organizationId, $request->facilityCode);
      if (null === $facilityId) {
        return new ProvisionEquipmentResult(
          ProvisionOutcome::INVALID,
          message: 'Unknown facility code "' . $request->facilityCode . '".',
        );
      }
    }

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
    } catch (EquipmentSerialNumberAlreadyExistsException|InvalidArgumentException|InvalidValueException $exception) {
      return new ProvisionEquipmentResult(ProvisionOutcome::INVALID, message: $exception->getMessage());
    } catch (MessengerRuntimeException $exception) {
      return $this->fromWrappedException($exception);
    }

    if (null !== $facilityId && !$request->dryRun) {
      return $this->assignToFacility($request, $result->equipmentId, $facilityId);
    }

    return new ProvisionEquipmentResult(ProvisionOutcome::CREATED, resourceId: $result->equipmentId);
  }

  /**
   * Method assignToFacility.
   *
   * Dispatches the existing `AssignToFacilityCommand` for an equipment item
   * that was just created — the same use case the HTTP API uses, so the
   * facility assignability rules (existence, organization, non-archived)
   * apply identically. Create and assign are two separate synchronous
   * commands, each with its own transaction: a failed assignment after a
   * successful creation leaves the equipment created but unassigned, and is
   * reported as an `INVALID` outcome naming both facts. This is deliberate —
   * duplicating the creation use case to gain a shared transaction would
   * create the parallel business-logic path the provisioning ports exist to
   * avoid, and the worst case (an unassigned item, visible in the row
   * report) is recoverable through the normal assignment endpoint.
   *
   * @since 1.1.0
   *
   * @param ProvisionEquipmentRequest $request the provisioning request
   * @param string $equipmentId the created equipment identifier
   * @param string $facilityId the resolved facility identifier
   *
   * @return ProvisionEquipmentResult the provisioning outcome
   */
  private function assignToFacility(ProvisionEquipmentRequest $request, string $equipmentId, string $facilityId): ProvisionEquipmentResult
  {
    try {
      $this->commandBus->dispatch(new AssignToFacilityCommand(
        organizationId: $request->organizationId,
        equipmentId: $equipmentId,
        facilityId: $facilityId,
      ));
    } catch (Throwable $exception) {
      $invalid = $this->findException($exception, InvalidValueException::class)
        ?? $this->findException($exception, InvalidArgumentException::class)
        ?? $exception;

      return new ProvisionEquipmentResult(
        ProvisionOutcome::INVALID,
        resourceId: $equipmentId,
        message: 'Equipment was created but could not be assigned to facility code "'
          . $request->facilityCode . '": ' . $invalid->getMessage(),
      );
    }

    return new ProvisionEquipmentResult(ProvisionOutcome::CREATED, resourceId: $equipmentId);
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

    // Both classes, because the handlers now throw `InvalidValueException`
    // while the validation ports still declare `InvalidArgumentException`.
    // Dropping either turns a graceful INVALID outcome — which Import's dry run
    // reports as a row-level error — into an exception escaping the service.
    $invalid = $this->findException($exception, InvalidValueException::class)
      ?? $this->findException($exception, InvalidArgumentException::class);
    if ($invalid instanceof InvalidValueException || $invalid instanceof InvalidArgumentException) {
      return new ProvisionEquipmentResult(ProvisionOutcome::INVALID, message: $invalid->getMessage());
    }

    throw $exception;
  }
  // #endregion
}
