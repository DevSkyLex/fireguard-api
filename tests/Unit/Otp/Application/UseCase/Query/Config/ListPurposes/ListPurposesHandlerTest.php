<?php

declare(strict_types=1);

namespace Tests\Unit\Otp\Application\UseCase\Query\Config\ListPurposes;

use Otp\Application\UseCase\Query\Config\ListPurposes\{ListPurposesHandler, ListPurposesQuery, ListPurposesResult};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

use function array_map;

/**
 * Test ListPurposesHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ListPurposesHandler::class)]
final class ListPurposesHandlerTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testInvokeReturnsPurposes(): void
  {
    $handler = new ListPurposesHandler();

    $result = $handler->__invoke(new ListPurposesQuery());

    self::assertInstanceOf(ListPurposesResult::class, $result);
    self::assertCount(6, $result->items);

    $values = array_map(fn ($item) => $item->value, $result->items);
    self::assertContains('login', $values);
  }
  // #endregion
}
