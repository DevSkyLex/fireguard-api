<?php

declare(strict_types=1);

namespace Tests\Unit\MaintenanceExport\Domain\ValueObject;

use MaintenanceExport\Domain\Exception\MaintenanceExportException;
use MaintenanceExport\Domain\ValueObject\ExportIdentity;
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\TestCase;

use function str_repeat;

/**
 * Class ExportIdentityTest
 *
 * Explicit ERP codes and bounded declarations cannot become ambiguous export targets.
 *
 * @category UnitTest
 */
final class ExportIdentityTest extends TestCase
{
  // #region Methods
  #[Test]
  public function validIdentifiersAndReferencesHaveCanonicalRepresentations(): void
  {
    self::assertSame('cab60000-0000-4000-8000-000000000001', ExportIdentity::uuid('CAB60000-0000-4000-8000-000000000001'));
    self::assertSame('sage_100.v1', ExportIdentity::system(' sage_100.v1 '));
    self::assertSame('équipement', ExportIdentity::text(' équipement ', 20));
    foreach (['customer', 'site', 'equipment'] as $type) {
      self::assertSame($type, ExportIdentity::resourceType($type));
    }
  }

  #[Test]
  #[DataProvider('invalidSystems')]
  public function erpSystemCodesMustBeExplicitAndBounded(string $system): void
  {
    $this->expectException(MaintenanceExportException::class);
    ExportIdentity::system($system);
  }

  /**
   * Method invalidSystems
   *
   * @return iterable<string,array{string}> ambiguous or oversized external system codes
   */
  public static function invalidSystems(): iterable
  {
    yield 'empty' => [''];
    yield 'whitespace' => ['two systems'];
    yield 'path' => ['../erp'];
    yield 'oversized' => [str_repeat('a', 41)];
  }

  #[Test]
  public function unsupportedReferenceResourcesAreRejected(): void
  {
    $this->expectException(MaintenanceExportException::class);
    ExportIdentity::resourceType('invoice');
  }

  #[Test]
  #[DataProvider('invalidTexts')]
  public function importReferenceRejectsEmptyControlCharactersAndCharacterOverflow(string $text, int $limit): void
  {
    $this->expectException(MaintenanceExportException::class);
    ExportIdentity::text($text, $limit);
  }

  /**
   * Method invalidTexts
   *
   * @return iterable<string,array{string,int}> invalid retained human declarations
   */
  public static function invalidTexts(): iterable
  {
    yield 'empty' => [' ', 200];
    yield 'nul' => ["ERP\0reference", 200];
    yield 'control' => ["ERP\x1Breference", 200];
    yield 'utf8 character overflow' => ['éééé', 3];
  }
  // #endregion
}
