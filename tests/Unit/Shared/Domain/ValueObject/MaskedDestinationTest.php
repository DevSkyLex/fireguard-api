<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Domain\ValueObject;

use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\ValueObject\MaskedDestination;

/**
 * Test MaskedDestinationTest.
 *
 * @category Unit Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: MaskedDestination::class)]
final class MaskedDestinationTest extends TestCase
{
  // #region Methods
  /**
   * Method testMaskEmail.
   *
   * Tests that maskEmail correctly masks email addresses.
   *
   * @dataProvider emailProvider
   */
  #[Test]
  #[DataProvider('emailProvider')]
  public function testMaskEmail(string $email, string $expected): void
  {
    $masked = MaskedDestination::maskEmail($email);
    self::assertSame($expected, $masked);
  }

  /**
   * Data provider for email masking tests.
   *
   * @return array<string, array{string, string}>
   */
  public static function emailProvider(): array
  {
    return [
      'standard email' => [
        'john.doe@example.com',
        'j******e@e*****e.com',
      ],
      'short email' => [
        'ab@cd.ef',
        'ab***@cd***.ef',
      ],
      'very short' => [
        'a@b.c',
        'a***@b***.c',
      ],
      'long email' => [
        'contact@valentin-fortin.pro',
        'c*****t@v*************n.pro',
      ],
    ];
  }

  /**
   * Method testMaskPhone.
   *
   * Tests that maskPhone correctly masks phone numbers.
   *
   * @dataProvider phoneProvider
   */
  #[Test]
  #[DataProvider('phoneProvider')]
  public function testMaskPhone(string $phone, string $expected): void
  {
    $masked = MaskedDestination::maskPhone($phone);
    self::assertSame($expected, $masked);
  }

  /**
   * Data provider for phone masking tests.
   *
   * @return array<string, array{string, string}>
   */
  public static function phoneProvider(): array
  {
    return [
      'french mobile' => [
        '+33612345678',
        '+33*****5678',
      ],
      'french mobile without prefix' => [
        '0612345678',
        '061***5678',
      ],
      'short number' => [
        '123',
        '***',
      ],
      'international' => [
        '+14155552671',
        '+14*****2671',
      ],
    ];
  }
  // #endregion
}
