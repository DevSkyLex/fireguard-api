<?php

declare(strict_types=1);

namespace Tests\Unit\Import\Domain\ValueObject;

use Import\Domain\ValueObject\ImportKind;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;

/**
 * Test ImportKind.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ImportKind::class)]
final class ImportKindTest extends TestCase
{
  #[Test]
  public function itPreservesKnownAndNewContributionIdentifiers(): void
  {
    self::assertSame('equipment', ImportKind::from('equipment')->value);
    self::assertSame('facility', ImportKind::from('facility')->value);
    self::assertSame('member', ImportKind::from('member')->value);
    self::assertSame('inspection', ImportKind::from('inspection')->value);
  }

  #[Test]
  public function itResolvesAValidIdentifierWithoutConsultingAClosedCatalogue(): void
  {
    self::assertEquals(ImportKind::from('custom_kind'), ImportKind::tryFrom('custom_kind'));
  }

  /**
   * @return iterable<string, array{string}> invalid storage identifiers
   */
  public static function invalidIdentifiers(): iterable
  {
    yield 'empty' => [''];
    yield 'whitespace' => [' '];
    yield 'uppercase' => ['Equipment'];
    yield 'punctuation' => ['custom-kind'];
    yield 'leading digit' => ['1kind'];
    yield 'too long' => ['abcdefghijklmnopq'];
  }

  #[Test]
  #[DataProvider('invalidIdentifiers')]
  public function itRejectsInvalidStorageIdentifiers(string $identifier): void
  {
    self::assertNull(ImportKind::tryFrom($identifier));
    $this->expectException(InvalidValueException::class);

    ImportKind::from($identifier);
  }
}
