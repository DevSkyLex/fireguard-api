<?php

declare(strict_types=1);

namespace Tests\Unit\Otp\Presentation\Api\Provider\Config;

use ApiPlatform\Metadata\GetCollection;
use Otp\Application\UseCase\Query\Config\ListPurposes\{ListPurposesResult, PurposeResult};
use Otp\Presentation\Api\Provider\Config\ListPurposesProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;

/**
 * Test ListPurposesProviderTest.
 *
 * @category Provider Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ListPurposesProvider::class)]
final class ListPurposesProviderTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testProvideMapsPurposes(): void
  {
    $result = new ListPurposesResult(items: [
      new PurposeResult('login', 'Login 2FA', 300, 5),
    ]);

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->willReturn($result);

    $provider = new ListPurposesProvider($queryBus);

    $output = $provider->provide(new GetCollection());

    self::assertCount(1, $output);
    self::assertSame('login', $output[0]->value);
    self::assertSame(300, $output[0]->ttlSeconds);
  }
  // #endregion
}
