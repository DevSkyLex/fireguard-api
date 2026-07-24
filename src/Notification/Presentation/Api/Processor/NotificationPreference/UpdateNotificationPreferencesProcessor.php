<?php

declare(strict_types=1);

namespace Notification\Presentation\Api\Processor\NotificationPreference;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeInterface;
use Notification\Application\UseCase\Command\Notification\UpdateNotificationPreferences\{
  UpdateNotificationPreferencesCommand,
  UpdateNotificationPreferencesResult
};
use Notification\Application\UseCase\Query\Notification\GetNotificationPreferences\NotificationPreferenceResult;
use Notification\Presentation\Api\Dto\Input\NotificationPreference\{NotificationPreferenceItemInput, UpdateNotificationPreferencesInput};
use Notification\Presentation\Api\Dto\Output\NotificationPreference\{NotificationPreferenceOutput, NotificationPreferencesOutput};
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

use function array_map;
use function trim;

/**
 * Processor UpdateNotificationPreferencesProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<UpdateNotificationPreferencesInput, NotificationPreferencesOutput|null>
 */
final readonly class UpdateNotificationPreferencesProcessor implements ProcessorInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus the command bus
   * @param Security $security the security service
   */
  public function __construct(
    private CommandBusPort $commandBus,
    private Security $security,
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
   * @param Operation $operation the operation metadata
   * @param array<string, mixed> $uriVariables URI variables
   * @param array<string, mixed> $context processor context
   *
   * @return NotificationPreferencesOutput|null the authenticated user's full customized preference set after the upsert
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?NotificationPreferencesOutput
  {
    if (!$data instanceof UpdateNotificationPreferencesInput) {
      return null;
    }

    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $entries = array_map(
      static fn (NotificationPreferenceItemInput $item): array => [
        'category' => trim($item->category),
        'emailEnabled' => $item->emailEnabled,
        'mercureEnabled' => $item->mercureEnabled,
      ],
      $data->preferences,
    );

    /** @var UpdateNotificationPreferencesResult $result */
    $result = $this->commandBus->dispatch(new UpdateNotificationPreferencesCommand(
      userId: $user->getId(),
      preferences: $entries,
    ));

    return $this->mapOutput($result);
  }

  /**
   * Method mapOutput.
   *
   * @since 1.0.0
   *
   * @param UpdateNotificationPreferencesResult $result the use case result
   *
   * @return NotificationPreferencesOutput the API output
   */
  private function mapOutput(UpdateNotificationPreferencesResult $result): NotificationPreferencesOutput
  {
    $output = new NotificationPreferencesOutput();
    $output->preferences = array_map(
      static fn (NotificationPreferenceResult $item): NotificationPreferenceOutput => self::mapItem($item),
      $result->preferences,
    );

    return $output;
  }

  /**
   * Method mapItem.
   *
   * @since 1.0.0
   *
   * @param NotificationPreferenceResult $item the use case item result
   *
   * @return NotificationPreferenceOutput the API item output
   */
  private static function mapItem(NotificationPreferenceResult $item): NotificationPreferenceOutput
  {
    $output = new NotificationPreferenceOutput();
    $output->category = $item->category;
    $output->emailEnabled = $item->emailEnabled;
    $output->mercureEnabled = $item->mercureEnabled;
    $output->updatedAt = $item->updatedAt->format(DateTimeInterface::ATOM);

    return $output;
  }
  // #endregion
}
