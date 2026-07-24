<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Domain\Model\Tag;

use DateTimeImmutable;
use Equipment\Domain\Model\Tag\Tag;
use Equipment\Domain\ValueObject\{EquipmentOrganizationId, TagId};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test TagTest.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(Tag::class)]
final class TagTest extends TestCase
{
  private const string TAG_ID = '550e8400-e29b-41d4-a716-446655442000';

  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655442001';

  #[Test]
  public function itCreatesTagWithGeneratedTimestamp(): void
  {
    $tag = Tag::create(
      id: TagId::fromString(self::TAG_ID),
      organizationId: EquipmentOrganizationId::fromString(self::ORG_ID),
      name: 'Critical',
    );

    self::assertSame(self::TAG_ID, $tag->id()->value);
    self::assertSame(self::ORG_ID, $tag->organizationId()->value);
    self::assertSame('Critical', $tag->name());
    self::assertInstanceOf(DateTimeImmutable::class, $tag->createdAt());
  }

  #[Test]
  public function itReconstitutesWithProvidedTimestamp(): void
  {
    $createdAt = new DateTimeImmutable('2026-02-20T08:30:00+00:00');

    $tag = Tag::reconstitute(
      id: TagId::fromString(self::TAG_ID),
      organizationId: EquipmentOrganizationId::fromString(self::ORG_ID),
      name: 'Outdoor',
      createdAt: $createdAt,
    );

    self::assertSame('Outdoor', $tag->name());
    self::assertSame($createdAt, $tag->createdAt());
  }
}
