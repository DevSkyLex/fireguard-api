<?php

declare(strict_types=1);

namespace Tests\Unit\User\Presentation\Api\Provider\User;

use ApiPlatform\Metadata\GetCollection;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Pagination\PaginatedResult;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Domain\ValueObject\Email;
use Tests\Helper\TestEventIdProvider;
use User\Domain\Model\User\User;
use User\Domain\ValueObject\{HashedPassword, UserId, UserProfile, Username};
use User\Presentation\Api\Provider\User\ListUsersProvider;

use function iterator_to_array;

/**
 * Test ListUsersProviderTest.
 *
 * @category Provider Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ListUsersProvider::class)]
final class ListUsersProviderTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testProvideMapsUsers(): void
  {
    $eventProvider = new TestEventIdProvider();
    $user = User::register(
      id: new UserId('550e8400-e29b-41d4-a716-446655440070'),
      username: new Username('jdoe'),
      email: new Email('jdoe@example.com'),
      password: HashedPassword::fromPlain('Password123!'),
      profile: new UserProfile('John', 'Doe', null),
      eventIdProvider: $eventProvider,
    );

    $result = new PaginatedResult(
      items: [$user],
      total: 1,
      limit: 10,
      offset: 0,
    );

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->willReturn($result);

    $provider = new ListUsersProvider($queryBus);

    $output = $provider->provide(new GetCollection());

    $items = iterator_to_array($output);
    self::assertCount(1, $items);
    self::assertSame('jdoe', $items[0]->username);
    self::assertSame('jdoe@example.com', $items[0]->email);
  }
  // #endregion
}
