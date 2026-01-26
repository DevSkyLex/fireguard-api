<?php

declare(strict_types=1);

namespace Tests\Unit\Otp\Presentation\Api\Provider\Config;

use ApiPlatform\Metadata\GetCollection;
use Otp\Application\UseCase\Query\Config\ListChannels\{ChannelResult, ListChannelsResult};
use Otp\Presentation\Api\Provider\Config\ListChannelsProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;

/**
 * Test ListChannelsProviderTest.
 *
 * @category Provider Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ListChannelsProvider::class)]
final class ListChannelsProviderTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testProvideMapsChannels(): void
  {
    $result = new ListChannelsResult(items: [
      new ChannelResult('email', 'Email', true),
    ]);

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->willReturn($result);

    $provider = new ListChannelsProvider($queryBus);

    $output = $provider->provide(new GetCollection());

    self::assertCount(1, $output);
    self::assertSame('email', $output[0]->value);
    self::assertTrue($output[0]->requiresDelivery);
  }
  // #endregion
}
