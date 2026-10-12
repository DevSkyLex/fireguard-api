<?php

declare(strict_types=1);

namespace Tests\Unit\Import\Application\Service;

use Import\Application\Service\ImportKindProcessorRegistry;
use LogicException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;
use Tests\Support\Import\RecordingImportKindProcessor;

/**
 * Test ImportKindProcessorRegistry.
 *
 * @category Service Tests
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ImportKindProcessorRegistry::class)]
final class ImportKindProcessorRegistryTest extends TestCase
{
  #[Test]
  public function itResolvesAnIndependentContributionAndPublishesItsMetadata(): void
  {
    $processor = new RecordingImportKindProcessor();
    $registry = new ImportKindProcessorRegistry([$processor]);

    self::assertSame($processor, $registry->get('test_asset'));
    self::assertSame($processor, $registry->find('test_asset'));
    self::assertEquals([$processor->descriptor()], $registry->descriptors());
    self::assertNull($registry->find('absent'));
  }

  #[Test]
  public function itRejectsDuplicateOwnersInsteadOfSelectingOne(): void
  {
    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('Duplicate import contributor: test_asset');

    new ImportKindProcessorRegistry([new RecordingImportKindProcessor(), new RecordingImportKindProcessor()]);
  }

  #[Test]
  public function itRejectsUnregisteredKindsAlthoughTheirIdentifiersAreValid(): void
  {
    $this->expectException(InvalidValueException::class);

    new ImportKindProcessorRegistry([])->get('test_asset');
  }
}
