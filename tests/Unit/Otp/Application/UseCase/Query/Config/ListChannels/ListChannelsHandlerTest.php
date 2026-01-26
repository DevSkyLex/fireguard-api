<?php

declare(strict_types=1);

namespace Tests\Unit\Otp\Application\UseCase\Query\Config\ListChannels;

use Otp\Application\UseCase\Query\Config\ListChannels\{ListChannelsHandler, ListChannelsQuery, ListChannelsResult};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ListChannelsHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ListChannelsHandler::class)]
final class ListChannelsHandlerTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testInvokeReturnsChannels(): void
  {
    $handler = new ListChannelsHandler();

    $result = $handler->__invoke(new ListChannelsQuery());

    self::assertInstanceOf(ListChannelsResult::class, $result);
    self::assertCount(3, $result->items);
    self::assertSame('email', $result->items[0]->value);
  }
  // #endregion
}
