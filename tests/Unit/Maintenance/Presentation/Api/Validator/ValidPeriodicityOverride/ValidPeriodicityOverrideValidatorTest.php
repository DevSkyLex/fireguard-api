<?php

declare(strict_types=1);

namespace Tests\Unit\Maintenance\Presentation\Api\Validator\ValidPeriodicityOverride;

use Maintenance\Presentation\Api\Validator\ValidPeriodicityOverride\{ValidPeriodicityOverride, ValidPeriodicityOverrideValidator};
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * Test ValidPeriodicityOverrideValidatorTest.
 *
 * The API contract must enforce exactly the domain's [P28D, P10Y] bounds:
 * a periodicity slipping past them would schedule regulatory inspections
 * outside the legal interval.
 *
 * @category Validator Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @extends ConstraintValidatorTestCase<ValidPeriodicityOverrideValidator>
 */
#[CoversClass(ValidPeriodicityOverrideValidator::class)]
final class ValidPeriodicityOverrideValidatorTest extends ConstraintValidatorTestCase
{
  /**
   * @return iterable<string, array{string}>
   */
  public static function acceptedPeriodicityProvider(): iterable
  {
    yield 'one month' => ['P1M'];
    yield 'ninety days' => ['P90D'];
    yield 'one year' => ['P1Y'];
    yield 'ten years' => ['P10Y'];
  }

  /**
   * @return iterable<string, array{string}>
   */
  public static function rejectedPeriodicityProvider(): iterable
  {
    yield 'below the one month floor' => ['P7D'];
    yield 'above the ten year ceiling' => ['P11Y'];
    yield 'not a duration' => ['every week'];
  }

  #[Test]
  public function testNullIsValid(): void
  {
    $this->validator->validate(null, new ValidPeriodicityOverride());

    $this->assertNoViolation();
  }

  #[Test]
  public function testBlankIsValid(): void
  {
    $this->validator->validate('', new ValidPeriodicityOverride());

    $this->assertNoViolation();
  }

  #[Test]
  public function testANonStringValueIsIgnored(): void
  {
    // Type enforcement belongs to the DTO's own type declaration; this
    // constraint only grades the interval itself.
    $this->validator->validate(42, new ValidPeriodicityOverride());

    $this->assertNoViolation();
  }

  #[Test]
  #[DataProvider('acceptedPeriodicityProvider')]
  public function testAnIntervalInsideTheDomainBoundsIsValid(string $value): void
  {
    $this->validator->validate($value, new ValidPeriodicityOverride());

    $this->assertNoViolation();
  }

  #[Test]
  #[DataProvider('rejectedPeriodicityProvider')]
  public function testAnIntervalOutsideTheDomainBoundsRaisesAViolation(string $value): void
  {
    $constraint = new ValidPeriodicityOverride();

    $this->validator->validate($value, $constraint);

    $this->buildViolation($constraint->message)
      ->setParameter('{{ value }}', $value)
      ->assertRaised();
  }

  #[Test]
  public function testItRefusesAConstraintItDoesNotOwn(): void
  {
    $this->expectException(UnexpectedTypeException::class);

    $this->validator->validate('P90D', new NotBlank());
  }

  protected function createValidator(): ValidPeriodicityOverrideValidator
  {
    return new ValidPeriodicityOverrideValidator();
  }
}
