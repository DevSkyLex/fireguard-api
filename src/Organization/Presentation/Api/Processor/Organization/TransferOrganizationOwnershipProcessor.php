<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Processor\Organization;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Organization\Application\UseCase\Command\Organization\TransferOrganizationOwnership\{
  TransferOrganizationOwnershipCommand,
  TransferOrganizationOwnershipResult
};
use Organization\Application\UseCase\Query\Organization\GetOrganization\{GetOrganizationQuery, GetOrganizationResult};
use Organization\Domain\Exception\{
  OrganizationAccessDeniedException,
  OrganizationArchivedException,
  OrganizationDeletionConfirmationMismatchException,
  OrganizationMemberNotFoundException,
  OrganizationNotFoundException,
  OrganizationOwnershipUnchangedException
};
use Organization\Domain\ValueObject\OrganizationSettings;
use Organization\Presentation\Api\Dto\Input\Organization\TransferOrganizationOwnershipInput;
use Organization\Presentation\Api\Dto\Output\Organization\{OrganizationOutput, OrganizationSettingsOutput};
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{
  AccessDeniedHttpException,
  BadRequestHttpException,
  ConflictHttpException,
  NotFoundHttpException,
  UnprocessableEntityHttpException
};
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Throwable;

use function is_string;

/**
 * Processor TransferOrganizationOwnershipProcessor.
 *
 * Transfers ownership of an organization from its current owner to another
 * active member on behalf of the authenticated user. Ownership transfer is
 * intentionally gated on the organization's current-owner identity rather
 * than on RBAC permissions — that check, and the danger-zone slug
 * confirmation (mirroring `DELETE /organizations/{id}`), are enforced by
 * `TransferOrganizationOwnershipHandler`; this processor only forwards the
 * request and maps the resulting domain failure to HTTP.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<TransferOrganizationOwnershipInput, OrganizationOutput>
 */
final readonly class TransferOrganizationOwnershipProcessor implements ProcessorInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * TransferOrganizationOwnershipProcessor class.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus the command bus
   * @param QueryBusPort $queryBus the query bus
   * @param Security $security the security service
   */
  public function __construct(
    private CommandBusPort $commandBus,
    private QueryBusPort $queryBus,
    private Security $security,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method process.
   *
   * Dispatches the ownership transfer command and returns the refreshed
   * organization output.
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
    /** @var TransferOrganizationOwnershipInput $data */
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $uriVariables['id'] ?? null;
    if (!is_string($organizationId) || '' === $organizationId) {
      throw new BadRequestHttpException('Organization identifier is required.');
    }

    try {
      /** @var TransferOrganizationOwnershipResult $result */
      $result = $this->commandBus->dispatch(new TransferOrganizationOwnershipCommand(
        organizationId: $organizationId,
        actingUserId: $user->getId(),
        newOwnerUserId: $data->newOwnerUserId,
        slugConfirmation: $data->slug,
      ));
    } catch (OrganizationDeletionConfirmationMismatchException $exception) {
      throw new UnprocessableEntityHttpException($exception->getMessage(), $exception);
    } catch (OrganizationAccessDeniedException $exception) {
      throw new AccessDeniedHttpException($exception->getMessage(), $exception);
    } catch (OrganizationNotFoundException|OrganizationMemberNotFoundException $exception) {
      throw new NotFoundHttpException($exception->getMessage(), $exception);
    } catch (OrganizationArchivedException|OrganizationOwnershipUnchangedException $exception) {
      throw new ConflictHttpException($exception->getMessage(), $exception);
    } catch (MessengerRuntimeException $exception) {
      $this->rethrowDomainFailure($exception);

      throw $exception;
    }

    return $this->buildOutput($result->organizationId);
  }

  /**
   * Method buildOutput.
   *
   * Re-reads the organization and maps it to the API output. The transfer
   * result only carries the previous/new owner ids and the transfer
   * timestamp, so the refreshed organization is re-read the same way
   * `UpdateOrganizationSettingsProcessor`/`ChangeOrganizationPlanProcessor`
   * do for every other mutating organization operation.
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
        if ($candidate instanceof OrganizationDeletionConfirmationMismatchException) {
          throw new UnprocessableEntityHttpException($candidate->getMessage(), $exception);
        }

        if ($candidate instanceof OrganizationAccessDeniedException) {
          throw new AccessDeniedHttpException($candidate->getMessage(), $exception);
        }

        if ($candidate instanceof OrganizationNotFoundException || $candidate instanceof OrganizationMemberNotFoundException) {
          throw new NotFoundHttpException($candidate->getMessage(), $exception);
        }

        if ($candidate instanceof OrganizationArchivedException || $candidate instanceof OrganizationOwnershipUnchangedException) {
          throw new ConflictHttpException($candidate->getMessage(), $exception);
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
