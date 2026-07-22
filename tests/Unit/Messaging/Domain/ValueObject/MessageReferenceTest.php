<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Domain\ValueObject;

use Messaging\Domain\Exception\MessagingValidationException;
use Messaging\Domain\ValueObject\MessageReference;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;

use function str_repeat;

/**
 * Test MessageReferenceTest.
 *
 * @category ValueObject Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MessageReference::class)]
final class MessageReferenceTest extends TestCase
{
  #[Test]
  public function testFromArrayBuildsAValidReference(): void
  {
    $reference = MessageReference::fromArray(['type' => 'facility', 'id' => 'facility-1', 'label' => 'Site Nord', 'code' => 'FAC-001']);

    self::assertSame('facility', $reference->type);
    self::assertSame('facility-1', $reference->id);
    self::assertSame('Site Nord', $reference->label);
    self::assertSame('FAC-001', $reference->code);
  }

  #[Test]
  public function testFromArrayAllowsOmittingLabelAndCode(): void
  {
    $reference = MessageReference::fromArray(['type' => 'equipment', 'id' => 'equipment-1']);

    self::assertNull($reference->label);
    self::assertNull($reference->code);
  }

  #[Test]
  public function testFromArrayTrimsTheId(): void
  {
    $reference = MessageReference::fromArray(['type' => 'intervention', 'id' => '  intervention-1  ']);

    self::assertSame('intervention-1', $reference->id);
  }

  /**
   * @return iterable<string, array{0: string}>
   */
  public static function allowedTypeProvider(): iterable
  {
    yield 'non_conformity' => ['non_conformity'];
    yield 'intervention' => ['intervention'];
    yield 'facility' => ['facility'];
    yield 'equipment' => ['equipment'];
  }

  #[Test]
  #[DataProvider('allowedTypeProvider')]
  public function testFromArrayAcceptsEveryAllowedType(string $type): void
  {
    $reference = MessageReference::fromArray(['type' => $type, 'id' => 'record-1']);

    self::assertSame($type, $reference->type);
  }

  #[Test]
  public function testFromArrayRejectsAnUnsupportedType(): void
  {
    $this->expectException(MessagingValidationException::class);

    MessageReference::fromArray(['type' => 'channel', 'id' => 'record-1']);
  }

  #[Test]
  public function testFromArrayRejectsABlankId(): void
  {
    $this->expectException(MessagingValidationException::class);

    MessageReference::fromArray(['type' => 'facility', 'id' => '   ']);
  }

  #[Test]
  public function testFromArrayRejectsAMissingId(): void
  {
    $this->expectException(MessagingValidationException::class);

    MessageReference::fromArray(['type' => 'facility']);
  }

  #[Test]
  public function testFromArrayRejectsALabelLongerThan255Characters(): void
  {
    $this->expectException(MessagingValidationException::class);

    MessageReference::fromArray(['type' => 'facility', 'id' => 'facility-1', 'label' => str_repeat('a', 256)]);
  }

  #[Test]
  public function testFromArrayRejectsACodeLongerThan64Characters(): void
  {
    $this->expectException(MessagingValidationException::class);

    MessageReference::fromArray(['type' => 'facility', 'id' => 'facility-1', 'code' => str_repeat('a', 65)]);
  }

  #[Test]
  public function testListFromArrayBuildsEveryReference(): void
  {
    $references = MessageReference::listFromArray([
      ['type' => 'facility', 'id' => 'facility-1'],
      ['type' => 'equipment', 'id' => 'equipment-1'],
    ]);

    self::assertCount(2, $references);
    self::assertSame('facility', $references[0]->type);
    self::assertSame('equipment', $references[1]->type);
  }

  #[Test]
  public function testListFromArrayAcceptsExactlyTheMaximum(): void
  {
    $items = [];
    for ($i = 0; $i < MessageReference::MAX_REFERENCES; ++$i) {
      $items[] = ['type' => 'facility', 'id' => 'facility-' . $i];
    }

    $references = MessageReference::listFromArray($items);

    self::assertCount(MessageReference::MAX_REFERENCES, $references);
  }

  #[Test]
  public function testListFromArrayRejectsMoreThanTheMaximum(): void
  {
    $items = [];
    for ($i = 0; $i < MessageReference::MAX_REFERENCES + 1; ++$i) {
      $items[] = ['type' => 'facility', 'id' => 'facility-' . $i];
    }

    $this->expectException(MessagingValidationException::class);

    MessageReference::listFromArray($items);
  }

  #[Test]
  public function testListFromArrayReturnsAnEmptyListForNoReferences(): void
  {
    self::assertSame([], MessageReference::listFromArray([]));
  }

  #[Test]
  public function testToArrayRoundTripsTheShape(): void
  {
    $reference = MessageReference::fromArray(['type' => 'non_conformity', 'id' => 'nc-1', 'label' => 'Fire extinguisher overdue', 'code' => 'NC-42']);

    self::assertSame(
      ['type' => 'non_conformity', 'id' => 'nc-1', 'label' => 'Fire extinguisher overdue', 'code' => 'NC-42'],
      $reference->toArray(),
    );
  }
}
