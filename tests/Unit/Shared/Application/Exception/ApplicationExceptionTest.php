<?php

declare(strict_types=1);

namespace Tests\Shared\Application\Exception;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Shared\Application\Exception\ApplicationException;

/**
 * Test ApplicationException
 * @final
 *
 * Test for the ApplicationException context method
 *
 * @category Application Exception Test
 * @package Tests\Shared\Application\Exception
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: ApplicationException::class)]
final class ApplicationExceptionTest extends TestCase
{
  //#region Methods
  /**
   * Method testContextReturnsEmptyArray
   *
   * Test context returns empty array
   *
   * @access public
   *
   * @return void No return value
   */
  #[Test]
  public function testContextReturnsEmptyArray(): void
  {
    $exception = new class () extends ApplicationException {};

    self::assertSame(
      expected: [],
      actual: $exception->context()
    );
  }
  //#endregion
}

