<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Processor\Organization;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\UseCase\Command\Organization\UpdateOrganizationSettings\{
  UpdateOrganizationSettingsCommand,
  UpdateOrganizationSettingsResult
};
use Organization\Application\UseCase\Query\Organization\GetOrganization\{GetOrganizationQuery, GetOrganizationResult};
use Organization\Domain\Exception\{OrganizationNotFoundException, OrganizationSlugAlreadyExistsException};
use Organization\Domain\ValueObject\OrganizationSettings;
use Organization\Presentation\Api\Dto\Input\Organization\{
  UpdateOrganizationNotificationsInput,
  UpdateOrganizationRegionalInput,
  UpdateOrganizationSettingsInput
};
use Organization\Presentation\Api\Dto\Output\Organization\{OrganizationOutput, OrganizationSettingsOutput};
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};
use Shared\Domain\Exception\InvalidValueException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{
  AccessDeniedHttpException,
  BadRequestHttpException,
  ConflictHttpException,
  NotFoundHttpException
};
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Throwable;
use ValueError;

use function is_string;

/**
 * Processor UpdateOrganizationSettingsProcessor.
 *
 * Applies general & branding settings to an organization and returns the
 * refreshed organization output.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<UpdateOrganizationSettingsInput, OrganizationOutput>
 */
final readonly class UpdateOrganizationSettingsProcessor implements ProcessorInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * UpdateOrganizationSettingsProcessor class.
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
   * Processes API input and dispatches the corresponding command.
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
    /** @var UpdateOrganizationSettingsInput $data */
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
      /** @var UpdateOrganizationSettingsResult $result */
      $result = $this->commandBus->dispatch(new UpdateOrganizationSettingsCommand(
        organizationId: $organizationId,
        name: $data->name,
        slug: $data->slug,
        description: $data->description,
        isActive: $data->isActive,
        notifications: self::mapNotifications($data->notifications),
        regional: self::mapRegional($data->regional),
      ));
    } catch (OrganizationSlugAlreadyExistsException $exception) {
      throw new ConflictHttpException($exception->getMessage(), $exception);
    } catch (OrganizationNotFoundException $exception) {
      throw new NotFoundHttpException($exception->getMessage(), $exception);
    } catch (InvalidArgumentException|InvalidValueException|ValueError $exception) {
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
    $output->createdAt = $result->createdAt->format('c');
    $output->updatedAt = $result->updatedAt->format('c');

    return $output;
  }

  /**
   * Method mapNotifications.
   *
   * @static
   *
   * Maps the optional notifications input to a partial snake_case section payload.
   *
   * @since 1.0.0
   *
   * @param ?UpdateOrganizationNotificationsInput $input the notifications input
   *
   * @return ?array<string, bool|null> the partial payload, or null when not provided
   */
  private static function mapNotifications(?UpdateOrganizationNotificationsInput $input): ?array
  {
    if (null === $input) {
      return null;
    }

    return [
      'email_enabled' => $input->emailEnabled,
      'in_app_enabled' => $input->inAppEnabled,
      'intervention_published' => $input->interventionPublished,
      'intervention_assigned' => $input->interventionAssigned,
      'inspection_due' => $input->inspectionDue,
      'non_conformity_opened' => $input->nonConformityOpened,
      'member_invited' => $input->memberInvited,
    ];
  }

  /**
   * Method mapRegional.
   *
   * @static
   *
   * Maps the optional regional input to a partial snake_case section payload.
   *
   * @since 1.0.0
   *
   * @param ?UpdateOrganizationRegionalInput $input the regional input
   *
   * @return ?array<string, string|null> the partial payload, or null when not provided
   */
  private static function mapRegional(?UpdateOrganizationRegionalInput $input): ?array
  {
    if (null === $input) {
      return null;
    }

    return [
      'timezone' => $input->timezone,
      'locale' => $input->locale,
      'date_format' => $input->dateFormat,
      'first_day_of_week' => $input->firstDayOfWeek,
      'measurement_system' => $input->measurementSystem,
    ];
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
        if ($candidate instanceof OrganizationSlugAlreadyExistsException) {
          throw new ConflictHttpException($candidate->getMessage(), $exception);
        }

        if ($candidate instanceof OrganizationNotFoundException) {
          throw new NotFoundHttpException($candidate->getMessage(), $exception);
        }

        if ($candidate instanceof InvalidArgumentException || $candidate instanceof InvalidValueException || $candidate instanceof ValueError) {
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
