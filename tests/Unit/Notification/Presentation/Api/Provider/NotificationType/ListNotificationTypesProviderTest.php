<?php

declare(strict_types=1);

namespace Tests\Unit\Notification\Presentation\Api\Provider\NotificationType;

use ApiPlatform\Metadata\GetCollection;
use Notification\Domain\ValueObject\NotificationType;
use Notification\Presentation\Api\Provider\NotificationType\ListNotificationTypesProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

use function array_column;
use function count;

/**
 * Test ListNotificationTypesProviderTest.
 *
 * The client builds its notification-preference screen from this endpoint,
 * so the catalogue it returns has to stay in step with the domain constants
 * — a type missing here is a preference the user can never switch off.
 *
 * @category Provider Tests
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ListNotificationTypesProvider::class)]
final class ListNotificationTypesProviderTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testProvideReturnsEveryKnownNotificationType(): void
  {
    $outputs = new ListNotificationTypesProvider()->provide(new GetCollection());

    self::assertCount(count(NotificationType::all()), $outputs);
    self::assertSame(NotificationType::all(), array_column($outputs, 'type'));
  }

  #[Test]
  public function testEachTypeCarriesItsDerivedCategory(): void
  {
    $outputs = new ListNotificationTypesProvider()->provide(new GetCollection());

    foreach ($outputs as $output) {
      self::assertSame(NotificationType::category($output->type), $output->category);
      self::assertNotSame('', $output->category);
    }
  }

  #[Test]
  public function testCategoriesGroupTheKnownPrefixes(): void
  {
    $categories = array_column(
      new ListNotificationTypesProvider()->provide(new GetCollection()),
      'category',
      'type',
    );

    self::assertSame(
      NotificationType::CATEGORY_ORGANIZATION,
      $categories[NotificationType::ORGANIZATION_INVITATION],
    );
    self::assertSame(
      NotificationType::CATEGORY_SYSTEM,
      $categories[NotificationType::SYSTEM_ANNOUNCEMENT],
    );
  }
  // #endregion
}
