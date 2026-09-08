<?php

declare(strict_types=1);

namespace Tests\Unit\Otp\Application\UseCase\Query\Config\ListPurposes;

use Otp\Application\UseCase\Query\Config\ListPurposes\{ListPurposesHandler, ListPurposesQuery, ListPurposesResult, PurposeResult};
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
    self::assertCount(7, $result->items);

    $values = array_map(static fn (PurposeResult $item): string => $item->value, $result->items);
    self::assertContains('login', $values);
    self::assertContains('email_ownership', $values);

    $ownership = null;
    foreach ($result->items as $item) {
      if ('email_ownership' === $item->value) {
        $ownership = $item;
      }
    }
    self::assertInstanceOf(PurposeResult::class, $ownership);
    self::assertSame(600, $ownership->ttlSeconds);
    self::assertSame(5, $ownership->maxAttempts);
  }
  // #endregion
}
