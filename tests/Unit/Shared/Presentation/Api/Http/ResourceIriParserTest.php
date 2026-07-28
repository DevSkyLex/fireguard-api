<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Presentation\Api\Http;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;
use Shared\Presentation\Api\Http\ResourceIriParser;

/**
 * Test ResourceIriParserTest.
 *
 * Every write endpoint that accepts a relation runs the client's value
 * through this parser before it becomes a command field. It has to accept
 * both shapes API Platform emits — a bare UUID and a full IRI — while
 * refusing an IRI belonging to a different resource, otherwise a caller
 * could smuggle one entity's id into another's slot.
 *
 * @category Http Tests
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ResourceIriParser::class)]
final class ResourceIriParserTest extends TestCase
{
  // #region Constants
  private const string UUID = '550e8400-e29b-41d4-a716-446655479001';

  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655479002';
  // #endregion

  // #region Methods
  /**
   * @return iterable<string, array{string}>
   */
  public static function malformedIriProvider(): iterable
  {
    yield 'empty' => [''];
    yield 'plain word' => ['organizations'];
    yield 'wrong resource' => ['/api/facilities/' . self::UUID];
    yield 'truncated uuid' => ['550e8400-e29b-41d4-a716'];
  }

  #[Test]
  public function testIdAcceptsABareUuid(): void
  {
    self::assertSame(self::UUID, ResourceIriParser::id(self::UUID, 'organizations'));
  }

  #[Test]
  public function testIdExtractsTheIdentifierFromAnIri(): void
  {
    self::assertSame(
      self::UUID,
      ResourceIriParser::id('/api/organizations/' . self::UUID, 'organizations'),
    );
  }

  #[Test]
  public function testIdAcceptsAHyphenatedResourceSegment(): void
  {
    self::assertSame(
      self::UUID,
      ResourceIriParser::id('/api/intervention-templates/' . self::UUID, 'intervention-templates'),
    );
  }

  #[Test]
  #[DataProvider('malformedIriProvider')]
  public function testIdRejectsAValueThatIsNeitherUuidNorMatchingIri(string $value): void
  {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Invalid organizations IRI.');

    ResourceIriParser::id($value, 'organizations');
  }

  #[Test]
  public function testMemberIdAcceptsABareUuid(): void
  {
    self::assertSame(self::UUID, ResourceIriParser::memberId(self::UUID));
  }

  #[Test]
  public function testMemberIdExtractsTheIdentifierFromANestedIri(): void
  {
    self::assertSame(
      self::UUID,
      ResourceIriParser::memberId('/api/organizations/' . self::ORGANIZATION_ID . '/members/' . self::UUID),
    );
  }

  #[Test]
  public function testMemberIdRejectsAnIriThatIsNotAMembership(): void
  {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Invalid organization member IRI.');

    ResourceIriParser::memberId('/api/organizations/' . self::ORGANIZATION_ID);
  }
  // #endregion
}
