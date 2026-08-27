<?php

declare(strict_types=1);

namespace Facility\Application\Service;

use Facility\Application\Contract\Provisioning\{ProvisionFacilityRequest, ProvisionFacilityResult, ProvisionOutcome};
use Facility\Application\Port\Inbound\FacilityProvisioningPort;
use Facility\Application\Port\Outbound\FacilityRepositoryPort;
use Facility\Application\UseCase\Command\Facility\CreateFacility\{CreateFacilityCommand, CreateFacilityResult};
use Facility\Domain\Exception\{
  FacilityArchivedException,
  FacilityCodeAlreadyExistsException,
  FacilityHierarchyException,
  FacilityNotFoundException
};
use Facility\Domain\ValueObject\FacilityOrganizationId;
use InvalidArgumentException;
use Organization\Application\Contract\Quota\OrganizationQuotaExceededException;
use Shared\Application\Exception\{MessengerExceptionUnwrapperTrait, MessengerRuntimeException};
use Shared\Application\Port\Inbound\CommandBusPort;
use Throwable;

use function in_array;

/**
 * Service FacilityProvisioningService.
 *
 * Implements {@see FacilityProvisioningPort} by resolving an optional
 * `parentCode` to a parent facility identifier (looked up inside this
 * module, via `FacilityRepositoryPort`) and then dispatching the existing
 * `CreateFacilityCommand` through the command bus — the same synchronous
 * path the HTTP API uses, so the transactional quota check
 * (`OrganizationQuotaPort::assertCanAdd()`) runs intact. Every failure the
 * command bus can raise for this command is translated into a typed
 * {@see ProvisionFacilityResult} outcome rather than rethrown, unwrapping
 * `MessengerRuntimeException` exactly like
 * `Equipment\Presentation\Api\Processor\Equipment\CreateEquipmentProcessor`
 * does — callers depend on this port and its contracts, never on
 * `CreateFacilityCommand` or any Facility Domain type directly.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FacilityProvisioningService implements FacilityProvisioningPort
{
  use MessengerExceptionUnwrapperTrait;

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus the command bus value
   * @param FacilityRepositoryPort $facilityRepository the facility repository port
   */
  public function __construct(
    private CommandBusPort $commandBus,
    private FacilityRepositoryPort $facilityRepository,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method provision.
   *
   * @since 1.0.0
   *
   * @param ProvisionFacilityRequest $request the provisioning request
   *
   * @return ProvisionFacilityResult the provisioning outcome
   */
  public function provision(ProvisionFacilityRequest $request): ProvisionFacilityResult
  {
    $parentFacilityId = null;

    if (null !== $request->parentCode && '' !== $request->parentCode) {
      $parentFacilityId = $this->resolveParentIdByCode($request->organizationId, $request->parentCode);
      if (null === $parentFacilityId && !$this->isKnownPendingCode($request)) {
        return new ProvisionFacilityResult(
          ProvisionOutcome::INVALID,
          message: 'Unknown parent facility code "' . $request->parentCode . '".',
        );
      }
      // A dry run whose parent code is not in the database yet but matches a
      // row already reported "would create" earlier in the same batch is
      // left unresolved on purpose: there is no real identifier to pass, and
      // the dry-run projection below never looks the parent up by id.
    }

    try {
      /** @var CreateFacilityResult $result */
      $result = $this->commandBus->dispatch(new CreateFacilityCommand(
        organizationId: $request->organizationId,
        type: $request->type,
        name: $request->name,
        parentFacilityId: $parentFacilityId,
        code: $request->code,
        address: $request->address,
        latitude: $request->latitude,
        longitude: $request->longitude,
        dryRun: $request->dryRun,
        quotaProjectionOffset: $request->quotaProjectionOffset,
      ));
    } catch (OrganizationQuotaExceededException $exception) {
      return new ProvisionFacilityResult(ProvisionOutcome::QUOTA_EXCEEDED, message: $exception->getMessage());
    } catch (
      FacilityCodeAlreadyExistsException|FacilityHierarchyException|FacilityArchivedException
      |FacilityNotFoundException|InvalidArgumentException $exception
    ) {
      return new ProvisionFacilityResult(ProvisionOutcome::INVALID, message: $exception->getMessage());
    } catch (MessengerRuntimeException $exception) {
      return $this->fromWrappedException($exception);
    }

    return new ProvisionFacilityResult(ProvisionOutcome::CREATED, resourceId: $result->facilityId);
  }

  /**
   * Method isKnownPendingCode.
   *
   * Reports whether the request's `parentCode` matches a row earlier in the
   * same dry-run batch that would itself be created — the caller (Import's
   * `ProcessImportJobHandler`) tracks that list as it walks the file, so a
   * parent ordered before its children resolves the same way a real run's
   * intra-file ordering does.
   *
   * @since 1.0.0
   *
   * @param ProvisionFacilityRequest $request the provisioning request
   *
   * @return bool true when the parent code is a known pending (dry-run only) code
   */
  private function isKnownPendingCode(ProvisionFacilityRequest $request): bool
  {
    if (!$request->dryRun || null === $request->knownPendingCodes) {
      return false;
    }

    return in_array($request->parentCode, $request->knownPendingCodes, true);
  }

  /**
   * Method resolveParentIdByCode.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the owning organization identifier
   * @param string $parentCode the parent facility code
   *
   * @return ?string the resolved parent facility identifier, or null when unknown
   */
  private function resolveParentIdByCode(string $organizationId, string $parentCode): ?string
  {
    $matches = $this->facilityRepository->findByOrganizationId(
      organizationId: FacilityOrganizationId::fromString($organizationId),
      includeArchived: false,
      code: $parentCode,
      limit: 1,
      offset: 0,
    );

    $parent = $matches[0] ?? null;

    return $parent?->id()->__toString();
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
   * @return ProvisionFacilityResult the provisioning outcome
   */
  private function fromWrappedException(MessengerRuntimeException $exception): ProvisionFacilityResult
  {
    $quota = $this->findException($exception, OrganizationQuotaExceededException::class);
    if ($quota instanceof OrganizationQuotaExceededException) {
      return new ProvisionFacilityResult(ProvisionOutcome::QUOTA_EXCEEDED, message: $quota->getMessage());
    }

    foreach ([
      FacilityCodeAlreadyExistsException::class,
      FacilityHierarchyException::class,
      FacilityArchivedException::class,
      FacilityNotFoundException::class,
      InvalidArgumentException::class,
    ] as $exceptionClass) {
      /** @var Throwable|null $found */
      $found = $this->findException($exception, $exceptionClass);
      if (null !== $found) {
        return new ProvisionFacilityResult(ProvisionOutcome::INVALID, message: $found->getMessage());
      }
    }

    throw $exception;
  }
  // #endregion
}
