<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Processor\Organization;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\Port\Outbound\PlanRepositoryPort;
use Organization\Application\UseCase\Command\Organization\ChangeOrganizationPlan\{
  ChangeOrganizationPlanCommand,
  ChangeOrganizationPlanResult
};
use Organization\Application\UseCase\Query\Organization\GetOrganization\{GetOrganizationQuery, GetOrganizationResult};
use Organization\Domain\Exception\{OrganizationNotFoundException, OrganizationPlanUsageExceededException, PlanNotFoundException};
use Organization\Domain\ValueObject\{OrganizationSettings, PlanId};
use Organization\Presentation\Api\Dto\Input\Organization\ChangeOrganizationPlanInput;
use Organization\Presentation\Api\Dto\Output\Organization\{OrganizationOutput, OrganizationSettingsOutput};
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{
  AccessDeniedHttpException,
  BadRequestHttpException,
  ConflictHttpException,
  NotFoundHttpException
};
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Throwable;

use function is_string;

/**
 * Processor ChangeOrganizationPlanProcessor.
 *
 * Assigns a subscription plan to an organization and returns the refreshed
 * organization output. Self-service: requires the organization.settings.write
 * permission.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<ChangeOrganizationPlanInput, OrganizationOutput>
 */
final readonly class ChangeOrganizationPlanProcessor implements ProcessorInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the ChangeOrganizationPlanProcessor class.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus the command bus
   * @param QueryBusPort $queryBus the query bus
   * @param OrganizationAuthorizationPort $authorization the organization authorization port
   * @param PlanRepositoryPort $planRepository the plan repository port
   * @param Security $security the security service
   */
  public function __construct(
    private CommandBusPort $commandBus,
    private QueryBusPort $queryBus,
    private OrganizationAuthorizationPort $authorization,
    private PlanRepositoryPort $planRepository,
    private Security $security,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method process.
   *
   * Processes API input and dispatches the change-plan command.
   *
   * @since 1.0.0
   *
   * @param mixed $data the input data
   * @param Operation $operation the API operation metadata
   * @param array<string, mixed> $uriVariables URI variables extracted from the request
   * @param array<string, mixed> $context processing context values
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): OrganizationOutput
  {
    /** @var ChangeOrganizationPlanInput $data */
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

    // Self-service may only assign the free (default) plan. Paid plans are sold
    // through Stripe Checkout and applied by the billing webhook, so this guard
    // closes the "free upgrade" hole without coupling Organization to Stripe.
    $plan = $this->planRepository->findById(PlanId::fromString($data->planId));

    if (null === $plan) {
      throw new NotFoundHttpException('Plan not found.');
    }

    if (!$plan->isDefault()) {
      throw new ConflictHttpException('Paid plans must be subscribed to through billing. Use the checkout flow to upgrade.');
    }

    try {
      /** @var ChangeOrganizationPlanResult $result */
      $result = $this->commandBus->dispatch(new ChangeOrganizationPlanCommand(
        organizationId: $organizationId,
        planId: $data->planId,
        acknowledgeOveruse: $data->acknowledgeOveruse,
      ));
    } catch (OrganizationNotFoundException|PlanNotFoundException $exception) {
      throw new NotFoundHttpException($exception->getMessage(), $exception);
    } catch (OrganizationPlanUsageExceededException $exception) {
      throw new ConflictHttpException($exception->getMessage(), $exception);
    } catch (InvalidArgumentException $exception) {
      throw new BadRequestHttpException($exception->getMessage(), $exception);
    } catch (MessengerRuntimeException $exception) {
      $this->rethrowDomainFailure($exception);

      throw $exception;
    }

    return $this->buildOutput($result->organizationId);
  }

  /**
   * Method buildOutput.
   *
   * Re-reads the organization and maps it to the API output.
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

  /**
   * Method rethrowDomainFailure.
   *
   * Unwraps a messenger runtime failure and rethrows the matching HTTP error.
   *
   * @since 1.0.0
   *
   * @param Throwable $exception the caught runtime exception
   */
  private function rethrowDomainFailure(Throwable $exception): void
  {
    $current = $exception;

    while (null !== $current) {
      foreach ($this->wrappedExceptions($current) as $candidate) {
        if ($candidate instanceof OrganizationNotFoundException || $candidate instanceof PlanNotFoundException) {
          throw new NotFoundHttpException($candidate->getMessage(), $exception);
        }

        if ($candidate instanceof OrganizationPlanUsageExceededException) {
          throw new ConflictHttpException($candidate->getMessage(), $exception);
        }

        if ($candidate instanceof InvalidArgumentException) {
          throw new BadRequestHttpException($candidate->getMessage(), $exception);
        }
      }

      $current = $current->getPrevious();
    }
  }

  /**
   * Method wrappedExceptions.
   *
   * Yields the exception itself and any handler-wrapped exceptions.
   *
   * @since 1.0.0
   *
   * @param Throwable $exception the exception to expand
   *
   * @return iterable<Throwable> the candidate exceptions
   */
  private function wrappedExceptions(Throwable $exception): iterable
  {
    yield $exception;

    if ($exception instanceof HandlerFailedException) {
      yield from $exception->getWrappedExceptions();
    }
  }
  // #endregion
}
