<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Idempotency;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\Idempotency\HmacIdempotencyFingerprintAdapter;
use stdClass;

use function base64_encode;
use function str_repeat;

use const INF;
use const NAN;

/** Exercises canonical and keyed request identity without retaining secret inputs. */
final class HmacIdempotencyFingerprintAdapterTest extends TestCase
{
  public function testCanonicalizesNestedMapsButPreservesListOrderAndTypes(): void
  {
    $adapter = new HmacIdempotencyFingerprintAdapter(base64_encode(str_repeat('h', 32)));
    self::assertSame($adapter->digest('input', ['b' => ['z' => 3, 'a' => 1], 'a' => [2, 1]]), $adapter->digest('input', ['a' => [2, 1], 'b' => ['a' => 1, 'z' => 3]]));
    self::assertNotSame($adapter->digest('input', [1, 2]), $adapter->digest('input', [2, 1]));
    self::assertNotSame($adapter->digest('input', 1), $adapter->digest('input', '1'));
    self::assertNotSame($adapter->digest('input', 1), $adapter->digest('input', 1.0));
  }

  public function testSeparatesProtocolPurposeAndDedicatedKeys(): void
  {
    $first = new HmacIdempotencyFingerprintAdapter(base64_encode(str_repeat('a', 32)));
    $second = new HmacIdempotencyFingerprintAdapter(base64_encode(str_repeat('b', 32)));
    self::assertNotSame($first->digest('scope', 'secret'), $first->digest('input', 'secret'));
    self::assertNotSame($first->digest('input', 'secret'), $second->digest('input', 'secret'));
    self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $first->digest('input', 'secret'));
  }

  #[DataProvider('invalidValues')]
  public function testRejectsNonCanonicalValues(mixed $value): void
  {
    $this->expectException(InvalidArgumentException::class);
    new HmacIdempotencyFingerprintAdapter(base64_encode(str_repeat('h', 32)))->digest('input', $value);
  }

  /**
   * @return iterable<string, array{mixed}>
   */
  public static function invalidValues(): iterable
  {
    yield 'object' => [new stdClass()];
    yield 'infinity' => [INF];
    yield 'not a number' => [NAN];
  }

  public function testRejectsMissingOrWeakHmacKey(): void
  {
    $this->expectException(InvalidArgumentException::class);
    new HmacIdempotencyFingerprintAdapter(base64_encode('short'));
  }

  public function testRejectsExcessiveNesting(): void
  {
    $value = null;
    for ($i = 0; $i < 66; ++$i) {
      $value = [$value];
    }
    $this->expectException(InvalidArgumentException::class);
    new HmacIdempotencyFingerprintAdapter(base64_encode(str_repeat('h', 32)))->digest('input', $value);
  }
}
