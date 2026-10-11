<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Application\Contract\Http;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Http\ParsedHeaderValue;
use stdClass;

/**
 * Class ParsedHeaderValueTest
 *
 * Covers conditional-request syntax and refusal of non-string header values.
 *
 * @category Unit Tests
 */
final class ParsedHeaderValueTest extends TestCase
{
  // #region Methods
  /**
   * Method invalidValues
   *
   * @access public
   *
   * @return iterable<string, array{mixed}>
   */
  public static function invalidValues(): iterable
  {
    yield 'integer' => [42];
    yield 'boolean' => [false];
    yield 'float' => [1.5];
    yield 'object' => [new stdClass()];
    yield 'null entry' => [['valid', null]];
    yield 'numeric entry' => [['valid', 42]];
    yield 'nested array' => [[['invalid']]];
  }

  /**
   * Method testConditionalHeadersKeepTheirExactBytes
   *
   * @access public
   *
   * @return void
   */
  public function testConditionalHeadersKeepTheirExactBytes(): void
  {
    $etag = 'W/"revision-2", "revision-3"';

    self::assertSame($etag, ParsedHeaderValue::normalize($etag));
    self::assertSame('bytes=0-9,20-', ParsedHeaderValue::normalize('bytes=0-9,20-'));
    self::assertNull(ParsedHeaderValue::normalize(null));
    self::assertSame([], ParsedHeaderValue::normalize([]));
    self::assertSame(['first', 'second'], ParsedHeaderValue::normalize(['primary' => 'first', 4 => 'second']));
  }

  /**
   * Method testInvalidValuesAreRefused
   *
   * @access public
   *
   * @param mixed $value an invalid parsed value
   *
   * @return void
   */
  #[DataProvider('invalidValues')]
  public function testInvalidValuesAreRefused(mixed $value): void
  {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Invalid header value.');

    ParsedHeaderValue::normalize($value);
  }
  // #endregion
}
