<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Domain\ValueObject;

use Messaging\Domain\ValueObject\MessagingSubjectType;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;

use function str_repeat;

/**
 * Test MessagingSubjectType.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MessagingSubjectType::class)]
final class MessagingSubjectTypeTest extends TestCase
{
  #[Test]
  public function testHistoricalKeysRemainByteForByteStable(): void
  {
    foreach (['facility', 'equipment', 'intervention', 'non_conformity', 'channel', 'direct'] as $key) {
      self::assertSame($key, MessagingSubjectType::from($key)->value);
    }
  }

  #[Test]
  public function testNewAndUnloadedOwnerKeysAreHydratedWithoutRegistration(): void
  {
    self::assertSame('service_request', MessagingSubjectType::from('service_request')->value);
    self::assertSame('retired_owner', MessagingSubjectType::from('retired_owner')->value);
    self::assertSame(str_repeat('a', 32), MessagingSubjectType::from(str_repeat('a', 32))->value);
  }

  /**
   * @return iterable<string, array{string}>
   */
  public static function malformedKeys(): iterable
  {
    foreach (['', ' Facility', 'facility ', 'Facility', 'non-conformity', '123', 'facility/path', str_repeat('a', 33)] as $key) {
      yield $key => [$key];
    }
  }

  #[Test]
  #[DataProvider('malformedKeys')]
  public function testMalformedKeyIsRejected(string $key): void
  {
    $this->expectException(InvalidValueException::class);

    MessagingSubjectType::from($key);
  }
}
