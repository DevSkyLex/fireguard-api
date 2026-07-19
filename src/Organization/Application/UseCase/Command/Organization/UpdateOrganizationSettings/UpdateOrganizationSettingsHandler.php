<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Organization\UpdateOrganizationSettings;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Organization\Application\Port\Outbound\OrganizationRepositoryPort;
use Organization\Domain\Event\Organization\{
  OrganizationRestoredEvent,
  OrganizationSettingsUpdatedEvent,
  OrganizationSuspendedEvent
};
use Organization\Domain\Exception\{
  OrganizationArchivedException,
  OrganizationNotFoundException,
  OrganizationSlugAlreadyExistsException
};
use Organization\Domain\ValueObject\{
  OrganizationAutomationSettings,
  OrganizationCountry,
  OrganizationId,
  OrganizationLegalType,
  OrganizationName,
  OrganizationNotificationSettings,
  OrganizationRegionalSettings,
  OrganizationRegistrationNumber,
  OrganizationSlug,
  OrganizationStatus,
  OrganizationVatNumber
};
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\{EventDispatcherPort, TransactionManagerPort};
use Throwable;

use function str_contains;
use function strtolower;

/**
 * UseCase UpdateOrganizationSettingsHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UpdateOrganizationSettingsHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the UpdateOrganizationSettingsHandler class.
   *
   * @since 1.0.0
   *
   * @param OrganizationRepositoryPort $organizationRepository the organization repository port
   * @param TransactionManagerPort $transactionManager the transaction manager
   * @param EventDispatcherPort $eventDispatcher the domain event dispatcher
   */
  public function __construct(
    private OrganizationRepositoryPort $organizationRepository,
    private TransactionManagerPort $transactionManager,
    private EventDispatcherPort $eventDispatcher,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Applies the requested general & branding changes to an organization.
   *
   * @since 1.0.0
   *
   * @param UpdateOrganizationSettingsCommand $command the command payload
   *
   * @return UpdateOrganizationSettingsResult the use case result
   */
  public function __invoke(UpdateOrganizationSettingsCommand $command): UpdateOrganizationSettingsResult
  {
    $organization = $this->organizationRepository->findById(OrganizationId::fromString($command->organizationId));

    if (null === $organization) {
      throw OrganizationNotFoundException::withId($command->organizationId);
    }

    $previousStatus = $organization->status();

    if (OrganizationStatus::ARCHIVED === $previousStatus && false === $command->isActive) {
      throw OrganizationArchivedException::cannotSuspend();
    }

    /** @var list<string> $changedFields */
    $changedFields = [];

    if (null !== $command->name) {
      $organization->rename(new OrganizationName($command->name));
      $changedFields[] = 'name';
    }

    $targetSlug = null;
    if (null !== $command->slug) {
      $targetSlug = new OrganizationSlug($command->slug);
      $organization->changeSlug($targetSlug);
      $changedFields[] = 'slug';
    }

    if (null !== $command->description) {
      $organization->changeDescription($command->description);
      $changedFields[] = 'description';
    }

    if (null !== $command->isActive) {
      $command->isActive ? $organization->activate() : $organization->deactivate();
    }

    if (null !== $command->logoUrl) {
      $organization->setLogoUrl($command->logoUrl);
      $changedFields[] = 'logo';
    }

    if (null !== $command->notifications) {
      $merged = self::mergeProvided($organization->settings()->notifications->toArray(), $command->notifications);
      $organization->updateNotificationSettings(OrganizationNotificationSettings::fromArray($merged));
      $changedFields[] = 'notifications';
    }

    if (null !== $command->regional) {
      $merged = self::mergeProvided($organization->settings()->regional->toArray(), $command->regional);
      $organization->updateRegionalSettings(OrganizationRegionalSettings::fromArray($merged));
      $changedFields[] = 'regional';
    }

    if (null !== $command->compliance) {
      $organization->updateComplianceSettings(
        $organization->settings()->compliance->mergedWith($command->compliance),
      );
      $changedFields[] = 'compliance';
    }

    if (null !== $command->automation) {
      $merged = self::mergeProvided($organization->settings()->automation->toArray(), $command->automation);
      $organization->updateAutomationSettings(OrganizationAutomationSettings::fromArray($merged));
      $changedFields[] = 'automation';
    }

    if (null !== $command->approval) {
      $organization->updateApprovalSettings(
        $organization->settings()->approval->mergedWith($command->approval),
      );
      $changedFields[] = 'approval';
    }

    if (null !== $command->assistant) {
      $organization->updateAssistantSettings(
        $organization->settings()->assistant->mergedWith($command->assistant),
      );
      $changedFields[] = 'assistant';
    }

    $legalChanged = false;

    if (null !== $command->country) {
      $organization->changeCountry('' === $command->country ? null : new OrganizationCountry($command->country));
      $legalChanged = true;
    }

    if (null !== $command->legalType) {
      $organization->changeLegalType('' === $command->legalType ? null : OrganizationLegalType::from($command->legalType));
      $legalChanged = true;
    }

    if (null !== $command->legalName) {
      $organization->changeLegalName($command->legalName);
      $legalChanged = true;
    }

    if (null !== $command->registrationNumber) {
      $organization->changeRegistrationNumber('' === $command->registrationNumber ? null : new OrganizationRegistrationNumber($command->registrationNumber));
      $legalChanged = true;
    }

    if (null !== $command->vatNumber) {
      $organization->changeVatNumber('' === $command->vatNumber ? null : new OrganizationVatNumber($command->vatNumber));
      $legalChanged = true;
    }

    if ($legalChanged) {
      $changedFields[] = 'legal';
    }

    try {
      $this->transactionManager->transactional(function () use ($organization): void {
        $this->organizationRepository->save($organization);
      });
    } catch (Throwable $exception) {
      if ($this->isDuplicateSlugConstraintViolation($exception)) {
        throw OrganizationSlugAlreadyExistsException::withSlug((string) ($targetSlug ?? $organization->slug()));
      }

      throw $exception;
    }

    if ([] !== $changedFields) {
      $this->eventDispatcher->dispatch(new OrganizationSettingsUpdatedEvent(
        organizationId: $command->organizationId,
        changedFields: $changedFields,
      ));
    }

    if (null !== $command->isActive) {
      if ($command->isActive && OrganizationStatus::ACTIVE !== $previousStatus) {
        $this->eventDispatcher->dispatch(new OrganizationRestoredEvent(
          organizationId: $command->organizationId,
          previousStatus: $previousStatus->value,
        ));
      } elseif (!$command->isActive && OrganizationStatus::SUSPENDED !== $previousStatus) {
        $this->eventDispatcher->dispatch(new OrganizationSuspendedEvent(
          organizationId: $command->organizationId,
        ));
      }
    }

    return new UpdateOrganizationSettingsResult(
      organizationId: (string) $organization->id(),
    );
  }

  /**
   * Method mergeProvided.
   *
   * @static
   *
   * Overlays the non-null entries of a partial section payload onto the current
   * section array, leaving any unspecified field unchanged.
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $current the current section array
   * @param array<string, mixed> $provided the partial section payload
   *
   * @return array<string, mixed> the merged section array
   */
  private static function mergeProvided(array $current, array $provided): array
  {
    foreach ($provided as $key => $value) {
      if (null !== $value) {
        $current[$key] = $value;
      }
    }

    return $current;
  }

  /**
   * Method isDuplicateSlugConstraintViolation.
   *
   * Detects whether a transactional failure was caused by the unique slug constraint.
   *
   * @since 1.0.0
   *
   * @param Throwable $exception the transactional exception
   *
   * @return bool true when the failure is caused by slug uniqueness
   */
  private function isDuplicateSlugConstraintViolation(Throwable $exception): bool
  {
    $current = $exception;

    while (null !== $current) {
      if ($current instanceof UniqueConstraintViolationException) {
        $message = strtolower($current->getMessage());

        if (str_contains($message, 'uniq_organization_slug') || (str_contains($message, 'organizations') && str_contains($message, 'slug'))) {
          return true;
        }
      }

      $current = $current->getPrevious();
    }

    return false;
  }
  // #endregion
}
