<?php

declare(strict_types=1);

namespace Tests\Shared\Infrastructure\Symfony\Adapter\Outbound;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Infrastructure\Symfony\Adapter\Outbound\UuidGeneratorAdapter;
use Shared\Infrastructure\Symfony\Exception\UuidGenerationException;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV7;

/**
 * Test UuidGeneratorAdapter
 * @final
 *
 * Test the UuidGeneratorAdapter class
 *
 * @category Infrastructure Test
 * @package Tests\Shared\Infrastructure\Symfony\Adapter\Outbound
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class UuidGeneratorAdapterTest extends TestCase
{
  //#region Methods
  /**
   * Method testGenerateProducesValidUuidV7
   * @method testGenerateProducesValidUuidV7(): void
   *
   * Test that the generate method
   * produces a valid UuidV7
   *
   * @access public
   *
   * @return void No return value
   */
  public function testGenerateProducesValidUuidV7(): void
  {
    // Create adapter
    $adapter = new UuidGeneratorAdapter();

    // Generate uuid
    $uuid = $adapter->generate();

    // Assert uuid is valid
    self::assertTrue(condition: Uuid::isValid(uuid: $uuid));

    // Assert uuid is instance of UuidV7
    self::assertInstanceOf(
      expected: UuidV7::class,
      actual: Uuid::fromString(uuid: $uuid)
    );
  }

  /**
   * Method testGenerateWrapsGeneratorFailures
   * @method testGenerateWrapsGeneratorFailures(): void
   *
   * Test that the generate method wraps
   * the generator failures
   *
   * @access public
   *
   * @return void No return value
   */
  public function testGenerateWrapsGeneratorFailures(): void
  {
    $adapter = new UuidGeneratorAdapter(
      generator: static function (): Uuid {
        throw new RuntimeException(message: 'fail');
      }
    );

    $this->expectException(exception: UuidGenerationException::class);

    $adapter->generate();
  }
  //#endregion
}
