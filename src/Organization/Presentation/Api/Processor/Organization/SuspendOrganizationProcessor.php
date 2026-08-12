<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Processor\Organization;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\UseCase\Command\Organization\SuspendOrganization\SuspendOrganizationCommand;
use Organization\Application\UseCase\Query\Organization\GetOrganization\{GetOrganizationQuery, GetOrganizationResult};
use Organization\Domain\Exception\{OrganizationArchivedException, OrganizationNotFoundException};
use Organization\Domain\ValueObject\OrganizationSettings;
use Organization\Presentation\Api\Dto\Output\Organization\{OrganizationOutput, OrganizationSettingsOutput};
use Organization\Presentation\Api\Support\UnwrapsOrganizationBusFailures;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, ConflictHttpException, NotFoundHttpException};

use function is_string;

/**
 * Processor SuspendOrganizationProcessor.
 *
 * Suspends an organization as an explicit, dedicated action, distinct from
 * (and coexisting with) the legacy `isActive: false` toggle on
 * `PATCH /organizations/{id}` — see MODULE.md. Gated by
 * `organization.settings.write`, the SAME permission the legacy toggle
 * already requires: gating this dedicated endpoint any stricter would be
 * bypassable through that path anyway, so the two must agree.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<mixed, OrganizationOutput>
 */
final readonly class SuspendOrganizationProcessor implements ProcessorInterface
{
  // #region Traits
  /**
   * Trait UnwrapsOrganizationBusFailures.
   *
   * The bus adapters wrap every handler failure into
   * `MessengerRuntimeException`, so the direct `catch` clauses only cover a
   * bare in-process throw. The `MessengerRuntimeException` clauses using
   * this trait are what map the real dispatch path.
   *
   * @see UnwrapsOrganizationBusFailures
   */
  use UnwrapsOrganizationBusFailures;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the SuspendOrganizationProcessor class.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus the command bus
   * @param QueryBusPort $queryBus the query bus
   * @param OrganizationAuthorizationPort $authorization the organization authorization port
   * @param Security $security the security service
   */
  public function __construct(
    private CommandBusPort $commandBus,
    private QueryBusPort $queryBus,
    private OrganizationAuthorizationPort $authorization,
    private Security $security,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method process.
   *
   * Dispatches the suspend command and returns the refreshed organization
   * output. Idempotent: a second call against an already-suspended
   * organization succeeds and returns 200 unchanged.
   *
   * @since 1.0.0
   *
   * @param mixed $data the input data (unused — input: false)
   * @param Operation $operation the API operation metadata
   * @param array<string, mixed> $uriVariables URI variables extracted from the request
   * @param array<string, mixed> $context processing context values
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): OrganizationOutput
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $uriVariables['id'] ?? null;
    if (!is_string($organizationId) || '' === $organizationId) {
      throw new BadRequestHttpException('Organization identifier is required.');
    }

    if (!$this->authorization->hasPermission($user->getId(), $organizationId, 'organization.settings.write')) {
      throw new AccessDeniedHttpException('Missing organization.settings.write permission.');
    }

    try {
      $this->commandBus->dispatch(new SuspendOrganizationCommand(
        organizationId: $organizationId,
        actingUserId: $user->getId(),
      ));
    } catch (OrganizationNotFoundException $exception) {
      throw new NotFoundHttpException($exception->getMessage(), $exception);
    } catch (OrganizationArchivedException $exception) {
      throw new ConflictHttpException($exception->getMessage(), $exception);
    } catch (MessengerRuntimeException $exception) {
      $notFound = $this->findWrappedException($exception, OrganizationNotFoundException::class);
      if (null !== $notFound) {
        throw new NotFoundHttpException($notFound->getMessage(), $exception);
      }

      $conflict = $this->findWrappedException($exception, OrganizationArchivedException::class);
      if (null !== $conflict) {
        throw new ConflictHttpException($conflict->getMessage(), $exception);
      }

      throw $exception;
    }

    return $this->buildOutput($organizationId);
  }

  /**
   * Method buildOutput.
   *
   * Re-reads the organization and maps it to the API output, the same
   * `buildOutput()` pattern `TransferOrganizationOwnershipProcessor`/
   * `UpdateOrganizationSettingsProcessor` use for every other mutating
   * organization operation.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   *
   * @return OrganizationOutput the refreshed organization output
   */
  private function buildOutput(string $organizationId): OrganizationOutput
  {
    try {
      /** @var GetOrganizationResult $result */
      $result = $this->queryBus->ask(new GetOrganizationQuery($organizationId));
    } catch (OrganizationNotFoundException $exception) {
      throw new NotFoundHttpException($exception->getMessage(), $exception);
    }

    $output = new OrganizationOutput();
    $output->id = $result->id;
    $output->name = $result->name;
    $output->slug = $result->slug;
    $output->ownerUserId = $result->ownerUserId;
    $output->createdByUserId = $result->createdByUserId;
    $output->status = $result->status;
    $output->isActive = $result->isActive;
    $output->description = $result->description;
    $output->logoUrl = $result->logoUrl;
    $output->memberCount = $result->memberCount;
    $output->settings = OrganizationSettingsOutput::fromDomain($result->settings ?? OrganizationSettings::default());
    $output->planId = $result->planId;
    $output->planName = $result->planName;
    $output->country = $result->country;
    $output->legalType = $result->legalType;
    $output->legalName = $result->legalName;
    $output->registrationNumber = $result->registrationNumber;
    $output->vatNumber = $result->vatNumber;
    $output->createdAt = $result->createdAt->format('c');
    $output->updatedAt = $result->updatedAt->format('c');

    return $output;
  }

  // #endregion
}
