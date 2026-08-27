<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Processor\Organization;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\Port\Outbound\EquipmentTypeCatalogPort;
use Organization\Application\UseCase\Command\Organization\UpdateOrganizationSettings\{
  UpdateOrganizationSettingsCommand,
  UpdateOrganizationSettingsResult
};
use Organization\Application\UseCase\Query\Organization\GetOrganization\{GetOrganizationQuery, GetOrganizationResult};
use Organization\Domain\Exception\{
  OrganizationArchivedException,
  OrganizationNotFoundException,
  OrganizationSlugAlreadyExistsException
};
use Organization\Presentation\Api\Dto\Input\Organization\{
  UpdateOrganizationApprovalInput,
  UpdateOrganizationAssistantInput,
  UpdateOrganizationAutomationInput,
  UpdateOrganizationComplianceInput,
  UpdateOrganizationNotificationsInput,
  UpdateOrganizationRegionalInput,
  UpdateOrganizationSettingsInput
};
use Organization\Presentation\Api\Dto\Output\Organization\OrganizationOutput;
use Organization\Presentation\Api\Support\UnwrapsOrganizationBusFailures;
use Organization\Presentation\Api\Trait\OrganizationOutputMapperTrait;
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
use ValueError;

use function array_key_exists;
use function array_keys;
use function implode;
use function in_array;
use function is_string;
use function sprintf;

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
  /**
   * Trait OrganizationOutputMapperTrait.
   *
   * @see OrganizationOutputMapperTrait
   */
  use OrganizationOutputMapperTrait;

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
   * Initializes a new instance of the
   * UpdateOrganizationSettingsProcessor class.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus the command bus
   * @param QueryBusPort $queryBus the query bus
   * @param OrganizationAuthorizationPort $authorization the organization authorization port
   * @param EquipmentTypeCatalogPort $equipmentTypeCatalog the equipment-type catalog port
   * @param Security $security the security service
   */
  public function __construct(
    private CommandBusPort $commandBus,
    private QueryBusPort $queryBus,
    private OrganizationAuthorizationPort $authorization,
    private EquipmentTypeCatalogPort $equipmentTypeCatalog,
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
        compliance: $this->mapCompliance($data->compliance),
        automation: self::mapAutomation($data->automation),
        approval: self::mapApproval($data->approval),
        assistant: self::mapAssistant($data->assistant),
        country: $data->country,
        legalType: $data->legalType,
        legalName: $data->legalName,
        registrationNumber: $data->registrationNumber,
        vatNumber: $data->vatNumber,
      ));
    } catch (OrganizationArchivedException|OrganizationSlugAlreadyExistsException $exception) {
      throw new ConflictHttpException($exception->getMessage(), $exception);
    } catch (OrganizationNotFoundException $exception) {
      throw new NotFoundHttpException($exception->getMessage(), $exception);
    } catch (InvalidArgumentException|InvalidValueException|ValueError $exception) {
      throw new BadRequestHttpException($exception->getMessage(), $exception);
    } catch (MessengerRuntimeException $exception) {
      $conflict = $this->findWrappedException($exception, OrganizationArchivedException::class)
        ?? $this->findWrappedException($exception, OrganizationSlugAlreadyExistsException::class);
      if (null !== $conflict) {
        throw new ConflictHttpException($conflict->getMessage(), $exception);
      }

      $notFound = $this->findWrappedException($exception, OrganizationNotFoundException::class);
      if (null !== $notFound) {
        throw new NotFoundHttpException($notFound->getMessage(), $exception);
      }

      $invalidArgument = $this->findWrappedException($exception, InvalidArgumentException::class)
        ?? $this->findWrappedException($exception, InvalidValueException::class)
        ?? $this->findWrappedException($exception, ValueError::class);
      if (null !== $invalidArgument) {
        throw new BadRequestHttpException($invalidArgument->getMessage(), $exception);
      }

      throw $exception;
    }

    return $this->buildOutput($result->organizationId, $user->getId());
  }

  /**
   * Method buildOutput.
   *
   * Re-reads the organization and maps it to the API output. Passing
   * `$callerUserId` through resolves `isOwner`/`roles` for the acting user
   * via `OrganizationCallerMembershipPort` (see `GetOrganizationHandler`).
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $callerUserId the acting user identifier
   *
   * @return OrganizationOutput the refreshed organization output
   */
  private function buildOutput(string $organizationId, string $callerUserId): OrganizationOutput
  {
    try {
      /** @var GetOrganizationResult $result */
      $result = $this->queryBus->ask(new GetOrganizationQuery($organizationId, callerUserId: $callerUserId));
    } catch (OrganizationNotFoundException $exception) {
      throw new NotFoundHttpException($exception->getMessage(), $exception);
    } catch (MessengerRuntimeException $exception) {
      $notFound = $this->findWrappedException($exception, OrganizationNotFoundException::class);
      if (null !== $notFound) {
        throw new NotFoundHttpException($notFound->getMessage(), $exception);
      }

      throw $exception;
    }

    return $this->buildOrganizationOutput($result);
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
      'non_conformity_sla_breached' => $input->nonConformitySlaBreached,
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
   * Method mapCompliance.
   *
   * Maps the optional compliance input to a partial snake_case section payload,
   * validating periodicity keys against the equipment-type catalog (the domain
   * value object cannot: it never depends on the Equipment module).
   *
   * @since 1.1.0
   *
   * @param ?UpdateOrganizationComplianceInput $input the compliance input
   *
   * @return ?array<string, mixed> the partial payload, or null when not provided
   */
  private function mapCompliance(?UpdateOrganizationComplianceInput $input): ?array
  {
    if (null === $input) {
      return null;
    }

    if (null !== $input->inspectionPeriodicityDefaults) {
      $knownTypes = $this->equipmentTypeCatalog->values();

      foreach (array_keys($input->inspectionPeriodicityDefaults) as $equipmentType) {
        if (!in_array((string) $equipmentType, $knownTypes, true)) {
          throw new BadRequestHttpException(sprintf(
            'Unknown equipment type "%s". Supported types: %s.',
            (string) $equipmentType,
            implode(', ', $knownTypes),
          ));
        }
      }
    }

    return [
      'non_conformity_sla_days' => $input->nonConformitySlaDays,
      'inspection_periodicity_defaults' => $input->inspectionPeriodicityDefaults,
      'reminder_window_days' => $input->reminderWindowDays,
    ];
  }

  /**
   * Method mapAutomation.
   *
   * @static
   *
   * Maps the optional automation input to a partial snake_case section payload.
   *
   * @since 1.1.0
   *
   * @param ?UpdateOrganizationAutomationInput $input the automation input
   *
   * @return ?array<string, bool|null> the partial payload, or null when not provided
   */
  private static function mapAutomation(?UpdateOrganizationAutomationInput $input): ?array
  {
    if (null === $input) {
      return null;
    }

    return [
      'auto_create_intervention_on_critical_nc' => $input->autoCreateInterventionOnCriticalNc,
    ];
  }

  /**
   * Method mapAssistant.
   *
   * @static
   *
   * Maps the optional assistant input to a partial snake_case section payload.
   *
   * The `model` key is only emitted when the caller actually sent one, because
   * `OrganizationAssistantSettings::mergedWith()` treats a present-but-null
   * `model` as "revert to the operator default" — emitting it unconditionally
   * would silently clear the override whenever any other assistant field is
   * patched. An empty string is the explicit way to revert.
   *
   * @since 1.3.0
   *
   * @param ?UpdateOrganizationAssistantInput $input the assistant input
   *
   * @return ?array<string, mixed> the partial payload, or null when not provided
   */
  private static function mapAssistant(?UpdateOrganizationAssistantInput $input): ?array
  {
    if (null === $input) {
      return null;
    }

    $payload = [
      'enabled' => $input->enabled,
      'temperature' => $input->temperature,
      'include_business_context' => $input->includeBusinessContext,
    ];

    if (null !== $input->model) {
      $payload['model'] = '' === $input->model ? null : $input->model;
    }

    return $payload;
  }

  /**
   * Method mapApproval.
   *
   * @static
   *
   * Maps the optional approval input to a partial snake_case section
   * payload. Each `actionRules` entry's camelCase fields are translated to
   * snake_case, only including the fields actually provided so an omitted
   * field is left unchanged by `OrganizationApprovalSettings::mergedWith()`
   * rather than being cleared.
   *
   * @since 1.2.0
   *
   * @param ?UpdateOrganizationApprovalInput $input the approval input
   *
   * @return ?array<string, mixed> the partial payload, or null when not provided
   */
  private static function mapApproval(?UpdateOrganizationApprovalInput $input): ?array
  {
    if (null === $input) {
      return null;
    }

    $actionRules = null;
    if (null !== $input->actionRules) {
      $actionRules = [];
      foreach ($input->actionRules as $actionType => $rule) {
        if (null === $rule) {
          $actionRules[$actionType] = null;

          continue;
        }

        $mapped = [];
        if (array_key_exists('enabled', $rule)) {
          $mapped['enabled'] = $rule['enabled'];
        }
        if (array_key_exists('minApproverRole', $rule)) {
          $mapped['min_approver_role'] = $rule['minApproverRole'];
        }
        if (array_key_exists('minSeverity', $rule)) {
          $mapped['min_severity'] = $rule['minSeverity'];
        }
        $actionRules[$actionType] = $mapped;
      }
    }

    return [
      'action_rules' => $actionRules,
      'allow_self_approval' => $input->allowSelfApproval,
      'approval_ttl_days' => $input->approvalTtlDays,
    ];
  }

  // #endregion
}
