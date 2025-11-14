<?php

declare(strict_types=1);

namespace Tests\Shared\Infrastructure\Symfony\Adapter\Outbound;

use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\Symfony\Adapter\Outbound\SystemClockAdapter;

/**
 * Test SystemClockAdapter
 * @final
 *
 * Test the SystemClockAdapter class.
 *
 * @category Infrastructure Test
 * @package Tests\Shared\Infrastructure\Symfony\Adapter\Outbound
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class SystemClockAdapterTest extends TestCase
{
  //#region Methods
  /**
   * Method testNowReturnsCurrentDateTime
   * @method testNowReturnsCurrentDateTime(): void
   *
   * Ensure that the now method returns a DateTimeImmutable
   * instance close to the current time.
   *
   * @access public
   *
   * @return void No return value
   */
  public function testNowReturnsCurrentDateTime(): void
  {
    // Arrange
    $adapter = new SystemClockAdapter();

    // Act
    $before = new \DateTimeImmutable();
    $result = $adapter->now();
    $after = new \DateTimeImmutable();

    // Assert
    self::assertInstanceOf(\DateTimeImmutable::class, $result);
    self::assertGreaterThanOrEqual($before, $result);
    self::assertLessThanOrEqual($after, $result);
  }
  //#endregion
}
