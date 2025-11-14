<?php

declare(strict_types=1);

namespace Tests\Shared\Application\Exception;

use PHPUnit\Framework\TestCase;
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
final class ApplicationExceptionTest extends TestCase
{
  //#region Methods
  /**
   * Method testContextReturnsEmptyArray
   * @method testContextReturnsEmptyArray():
   *
   * Test context returns empty array
   *
   * @access public
   *
   * @return void No return value
   */
  public function testContextReturnsEmptyArray(): void
  {
    $exception = new class() extends ApplicationException {};

    self::assertSame(
      expected: [],
      actual: $exception->context()
    );
  }
  //#endregion
}
