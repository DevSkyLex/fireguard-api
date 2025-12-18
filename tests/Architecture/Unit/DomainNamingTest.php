<?php

declare(strict_types=1);

namespace App\Tests\Architecture\Unit;

use App\Tests\Architecture\Support\{ModuleCollection, ArchitectureLayer};
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Test DomainNamingTest
 * @final
 *
 * Ensures domain layer classes follow naming conventions:
 * - Events must end with "Event"
 * - Exceptions must end with "Exception"
 *
 * @category Architecture Unit Tests
 * @package App\Tests\Architecture\Unit
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class DomainNamingTest extends TestCase
{
  //#region Methods
  /**
   * Method testEventsEndWithEventSuffix
   *
   * Ensures every class in Domain/Event/ 
   * ends with "Event".
   *
   * @access public
   *
   * @return void No return value.
   */
  #[Test]
  public function testEventsEndWithEventSuffix(): void
  {
    $this->assertDomainNaming('Event', 'Event');
  }

  /**
   * Method testExceptionsEndWithExceptionSuffix
   *
   * Ensures every class in Domain/Exception/ ends with "Exception".
   *
   * @access public
   *
   * @return void No return value.
   */
  #[Test]
  public function testExceptionsEndWithExceptionSuffix(): void
  {
    $this->assertDomainNaming('Exception', 'Exception');
  }

  /**
   * Method assertDomainNaming
   *
   * Asserts that all files in a domain subdirectory follow the naming convention.
   *
   * @access private
   *
   * @param string $directory The subdirectory name under Domain/.
   * @param string $suffix The required suffix.
   *
   * @return void No return value.
   */
  private function assertDomainNaming(string $directory, string $suffix): void
  {
    $violations = [];

    foreach (ModuleCollection::all() as $module) {
      if (!$module->hasLayer(ArchitectureLayer::DOMAIN)) {
        continue;
      }

      $targetDir = $module->layerPath(ArchitectureLayer::DOMAIN)
        . DIRECTORY_SEPARATOR . $directory;

      if (!is_dir($targetDir)) {
        continue;
      }

      $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($targetDir)
      );

      foreach ($iterator as $file) {
        if (!$file instanceof \SplFileInfo) {
          continue;
        }
        if (!$file->isFile() || $file->getExtension() !== 'php') {
          continue;
        }

        $shortName = $file->getBasename('.php');

        if (!str_ends_with($shortName, $suffix)) {
          $violations[] = sprintf(
            '%s\\Domain\\%s\\%s must end with suffix "%s".',
            $module->namespace,
            $directory,
            $shortName,
            $suffix
          );
        }
      }
    }

    self::assertSame(
      expected: [],
      actual: $violations,
      message: sprintf('Every class in Domain/%s/ must end with suffix "%s".', $directory, $suffix)
    );
  }
  //#endregion
}
