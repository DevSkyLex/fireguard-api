<?php

declare(strict_types=1);

namespace App\Tests\Architecture\Unit;

use Deptrac\Deptrac\Contract\Config\DeptracConfig;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function basename;
use function chr;
use function count;
use function dirname;
use function glob;
use function preg_match;

/**
 * Prevents an empty collector or a permissive module rule from making the gate vacuous.
 *
 * @category Architecture Unit Tests
 * @version 1.0.0
 */
final class ModuleBoundaryConfigurationTest extends TestCase
{
  /**
   * Method vendorImageNamespaceDoesNotGrantAccessToPrivateInterventionClasses
   *
   * Proves the exact Composer-owned vendor prefix is uncollected while internal
   * image infrastructure and existing Intervention records remain private.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function vendorImageNamespaceDoesNotGrantAccessToPrivateInterventionClasses(): void
  {
    $root = dirname(__DIR__, 3);
    $config = new DeptracConfig();
    /** @var callable(DeptracConfig): void $configure */
    $configure = require $root . '/tests/Architecture/deptrac/modules.php';
    $configure($config);
    /** @var array{layers: array<string, array{collectors: list<array{value: string}>}>, ruleset: array<string, array<int|string, string>>} $definition */
    $definition = $config->toArray();
    $public = $definition['layers']['InterventionPublic']['collectors'][0]['value'];
    $private = $definition['layers']['InterventionPrivate']['collectors'][0]['value'];

    foreach ([
      'Intervention\\Image\\ImageManager',
      'Intervention\\Image\\Drivers\\Gd\\Driver',
      'Intervention\\Image\\Drivers\\Gd\\Decoders\\BinaryImageDecoder',
      'Intervention\\Image\\Exceptions\\DecoderException',
    ] as $vendorClass) {
      self::assertSame(0, preg_match('/' . $public . '/', $vendorClass));
      self::assertSame(0, preg_match('/' . $private . '/', $vendorClass));
    }

    foreach ([
      'Intervention\\Infrastructure\\Image\\PrivateRecord',
      'Intervention\\Infrastructure\\Persistence\\Doctrine\\Record\\InterventionRecord',
    ] as $privateClass) {
      self::assertSame(0, preg_match('/' . $public . '/', $privateClass));
      self::assertSame(1, preg_match('/' . $private . '/', $privateClass));
    }
    foreach (['UserPrivate', 'UserPublic', 'OrganizationPrivate', 'OrganizationPublic'] as $consumer) {
      self::assertNotContains('InterventionPrivate', $definition['ruleset'][$consumer]);
    }
  }

  #[Test]
  public function everyDocumentedModuleHasDisjointPublicAndPrivateCollectors(): void
  {
    $root = dirname(__DIR__, 3);
    $config = new DeptracConfig();
    /** @var callable(DeptracConfig): void $configure */
    $configure = require $root . '/tests/Architecture/deptrac/modules.php';
    $configure($config);
    /** @var array{layers: array<string, array{collectors: list<array{value: string}>}>, ruleset: array<string, array<int|string, string>>} $definition */
    $definition = $config->toArray();
    $modules = glob($root . '/src/*/MODULE.md') ?: [];
    self::assertNotEmpty($modules);
    self::assertCount(count($modules) * 2, $definition['layers']);
    foreach ($modules as $document) {
      $module = basename(dirname($document));
      $public = $definition['layers'][$module . 'Public']['collectors'][0]['value'];
      $private = $definition['layers'][$module . 'Private']['collectors'][0]['value'];
      foreach (['Application\Port\Inbound\ExamplePort', 'Application\Contract\ExampleResult'] as $type) {
        self::assertSame(1, preg_match('/' . $public . '/', $module . chr(92) . $type));
        self::assertSame(0, preg_match('/' . $private . '/', $module . chr(92) . $type));
      }
      foreach (['Infrastructure\Repository\PrivateRecord', 'Application\UseCase\PrivateHandler', 'Application\Service\PrivateService'] as $type) {
        self::assertSame(1, preg_match('/' . $private . '/', $module . chr(92) . $type));
        self::assertSame(0, preg_match('/' . $public . '/', $module . chr(92) . $type));
      }
      foreach (['Public', 'Private'] as $visibility) {
        $allowed = $definition['ruleset'][$module . $visibility];
        foreach ($modules as $otherDocument) {
          $other = basename(dirname($otherDocument));
          self::assertContains($other . 'Public', $allowed);
          if ($module !== $other) {
            self::assertNotContains($other . 'Private', $allowed);
          }
        }
      }
    }
  }
}
