<?php

declare(strict_types=1);

namespace Tests\Shared\Infrastructure\Exception;

use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\Exception\InfrastructureException;

/**
 * Test InfrastructureException
 * @final
 *
 * @category Infrastructure Exception Test
 * @package Tests\Shared\Infrastructure\Exception
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InfrastructureExceptionTest extends TestCase
{
  //#region Methods
  /**
   * Method testMetadataReturnsEmptyArray
   * @method testMetadataReturnsEmptyArray(): void
   *
   * Test metadata returns empty array
   *
   * @access public
   *
   * @return void No return value
   */
  public function testMetadataReturnsEmptyArray(): void
  {
    $exception = new class() extends InfrastructureException {};

    self::assertSame(
      expected: [],
      actual: $exception->metadata()
    );
  }
  //#endregion
}
