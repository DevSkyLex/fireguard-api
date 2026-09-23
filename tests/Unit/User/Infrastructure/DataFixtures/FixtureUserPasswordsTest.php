<?php

declare(strict_types=1);

namespace Tests\Unit\User\Infrastructure\DataFixtures;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;
use User\Infrastructure\DataFixtures\FixtureUserPasswords;

use function str_repeat;

/**
 * Test FixtureUserPasswordsTest.
 *
 * @category DataFixtures Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(FixtureUserPasswords::class)]
final class FixtureUserPasswordsTest extends TestCase
{
  #[Test]
  public function testReadsAllFiveDistinctPasswords(): void
  {
    $passwords = FixtureUserPasswords::fromJson(
      '{"admin":"admin-secret-1234","test":"test-secret-12345","demo":"demo-secret-12345","staff":"staff-secret-1234","dev_client":"client-secret-123"}',
      16,
    );

    self::assertSame('admin-secret-1234', $passwords->admin);
    self::assertSame('test-secret-12345', $passwords->test);
    self::assertSame('demo-secret-12345', $passwords->demo);
    self::assertSame('staff-secret-1234', $passwords->staff);
    self::assertSame('client-secret-123', $passwords->devClient);
  }

  /**
   * @return iterable<string, array{string}>
   */
  public static function invalidPasswordSets(): iterable
  {
    yield 'malformed JSON' => ['{'];
    yield 'non-object JSON' => ['null'];
    yield 'missing group' => ['{"admin":"a","test":"b","demo":"c","staff":"d"}'];
    yield 'extra group' => ['{"admin":"a","test":"b","demo":"c","staff":"d","dev_client":"e","extra":"f"}'];
    yield 'blank group' => ['{"admin":"a","test":"b","demo":"c","staff":" ","dev_client":"e"}'];
    yield 'non-string group' => ['{"admin":"a","test":"b","demo":"c","staff":123,"dev_client":"e"}'];
    yield 'reused password' => ['{"admin":"a","test":"b","demo":"c","staff":"a","dev_client":"e"}'];
    yield 'too short for development' => ['{"admin":"admin-secret-1234","test":"test-secret-12345","demo":"demo-secret-12345","staff":"short","dev_client":"client-secret-123"}'];
    yield 'bcrypt would truncate' => ['{"admin":"admin-secret-1234","test":"test-secret-12345","demo":"demo-secret-12345","staff":"' . str_repeat('x', 73) . '","dev_client":"client-secret-123"}'];
    yield 'NUL cannot be typed at login' => ['{"admin":"admin-secret-1234","test":"test-secret-12345","demo":"demo-secret-12345","staff":"staff-secret-1234\u0000","dev_client":"client-secret-123"}'];
  }

  #[Test]
  #[DataProvider('invalidPasswordSets')]
  public function testRejectsInvalidCredentialsWithoutLeakingValues(string $json): void
  {
    try {
      FixtureUserPasswords::fromJson($json, 16);
      self::fail('The fixture password configuration must fail before any database purge.');
    } catch (InvalidArgumentException $exception) {
      self::assertStringNotContainsString('admin-secret-1234', $exception->getMessage());
      self::assertStringNotContainsString('test-secret-12345', $exception->getMessage());
      self::assertStringNotContainsString('client-secret-123', $exception->getMessage());
    }
  }
}
