<?php

declare(strict_types=1);

namespace Compliance\Presentation\Api\Processor\Snapshot;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Compliance\Application\UseCase\Command\Snapshot\CreateSafetyRegisterSnapshot\{CreateSafetyRegisterSnapshotCommand, CreateSafetyRegisterSnapshotResult};
use Compliance\Presentation\Api\Dto\Input\Snapshot\CreateSafetyRegisterSnapshotInput;
use Compliance\Presentation\Api\Dto\Output\Snapshot\SafetyRegisterSnapshotOutput;
use Compliance\Presentation\Api\Factory\SafetyRegisterSnapshotOutputFactory;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

use function is_string;

/**
 * Processor CreateSafetyRegisterSnapshotProcessor.
 *
 * Handles `POST /organizations/{organizationId}/compliance/register-snapshots`:
 * unwraps the Input DTO and dispatches `CreateSafetyRegisterSnapshotCommand`.
 * The handler owns every decision — permission, entitlement, rendering,
 * hashing, persistence, audit event. Domain exceptions map to HTTP through
 * the central `api_platform.exception_to_status` configuration; no catch
 * here by design.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<CreateSafetyRegisterSnapshotInput, SafetyRegisterSnapshotOutput>
 */
final readonly class CreateSafetyRegisterSnapshotProcessor implements ProcessorInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus the command bus
   * @param Security $security the security helper
   * @param SafetyRegisterSnapshotOutputFactory $outputFactory the output factory
   */
  public function __construct(
    private CommandBusPort $commandBus,
    private Security $security,
    private SafetyRegisterSnapshotOutputFactory $outputFactory,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method process.
   *
   * @since 1.0.0
   *
   * @param mixed $data the input data
   * @param Operation $operation the API operation metadata
   * @param array<string, mixed> $uriVariables URI variables extracted from the request
   * @param array<string, mixed> $context processing context values
   *
   * @return SafetyRegisterSnapshotOutput the archived snapshot metadata
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): SafetyRegisterSnapshotOutput
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $uriVariables['organizationId'] ?? null;
    if (!is_string($organizationId) || '' === $organizationId) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $facilityId = $data instanceof CreateSafetyRegisterSnapshotInput ? $data->facilityId : null;

    /** @var CreateSafetyRegisterSnapshotResult $result */
    $result = $this->commandBus->dispatch(new CreateSafetyRegisterSnapshotCommand(
      organizationId: $organizationId,
      facilityId: is_string($facilityId) && '' !== $facilityId ? $facilityId : null,
      userId: $user->getId(),
    ));

    return $this->outputFactory->fromView($result->snapshot);
  }
  // #endregion
}
