<?php

declare(strict_types=1);

namespace Notification\Presentation\Api\Provider\NotificationPreference;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeInterface;
use Notification\Application\UseCase\Query\Notification\GetNotificationPreferences\{
  GetNotificationPreferencesQuery,
  GetNotificationPreferencesResult,
  NotificationPreferenceResult
};
use Notification\Presentation\Api\Dto\Output\NotificationPreference\{NotificationPreferenceOutput, NotificationPreferencesOutput};
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

use function array_map;

/**
 * Provider GetNotificationPreferencesProvider.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<NotificationPreferencesOutput>
 */
final readonly class GetNotificationPreferencesProvider implements ProviderInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param QueryBusPort $queryBus the query bus
   * @param Security $security the security service
   */
  public function __construct(
    private QueryBusPort $queryBus,
    private Security $security,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method provide.
   *
   * @since 1.0.0
   *
   * @param Operation $operation the operation metadata
   * @param array<string, mixed> $uriVariables URI variables
   * @param array<string, mixed> $context provider context
   *
   * @return NotificationPreferencesOutput the authenticated user's customized preferences
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): NotificationPreferencesOutput
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    /** @var GetNotificationPreferencesResult $result */
    $result = $this->queryBus->ask(new GetNotificationPreferencesQuery(userId: $user->getId()));

    return $this->mapOutput($result);
  }

  /**
   * Method mapOutput.
   *
   * @since 1.0.0
   *
   * @param GetNotificationPreferencesResult $result the use case result
   *
   * @return NotificationPreferencesOutput the API output
   */
  private function mapOutput(GetNotificationPreferencesResult $result): NotificationPreferencesOutput
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
