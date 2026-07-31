<?php

declare(strict_types=1);

namespace Notification\Presentation\Api\Provider\NotificationType;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Notification\Application\Contract\Notification\NotificationType;
use Notification\Presentation\Api\Dto\Output\NotificationType\NotificationTypeOutput;

use function array_map;

/**
 * Provider ListNotificationTypesProvider.
 *
 * Returns the list of all known notification types from the
 * NotificationType value object constant registry.
 * No database call — types are static by design.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<NotificationTypeOutput>
 */
final readonly class ListNotificationTypesProvider implements ProviderInterface
{
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
   * @return list<NotificationTypeOutput> all known notification types
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
  {
    return array_map(
      static fn (string $type): NotificationTypeOutput => self::mapOutput($type),
      NotificationType::all(),
    );
  }

  /**
   * Method mapOutput.
   *
   * @since 1.0.0
   *
   * @param string $type the raw type constant value
   *
   * @return NotificationTypeOutput the API output
   */
  private static function mapOutput(string $type): NotificationTypeOutput
  {
    $output = new NotificationTypeOutput();
    $output->type = $type;
    $output->category = NotificationType::category($type);

    return $output;
  }
  // #endregion
}
