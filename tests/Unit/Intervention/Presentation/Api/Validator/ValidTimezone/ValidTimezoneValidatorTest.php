<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Presentation\Api\Validator\ValidTimezone;

use Intervention\Presentation\Api\Validator\ValidTimezone\{ValidTimezone, ValidTimezoneValidator};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * Test ValidTimezoneValidatorTest.
 *
 * @category Validator Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @extends ConstraintValidatorTestCase<ValidTimezoneValidator>
 */
#[CoversClass(ValidTimezoneValidator::class)]
final class ValidTimezoneValidatorTest extends ConstraintValidatorTestCase
{
  // #region Methods
  #[Test]
  public function testNullIsValid(): void
  {
    $this->validator->validate(null, new ValidTimezone());

    $this->assertNoViolation();
  }

  #[Test]
  public function testBlankIsValid(): void
  {
    $this->validator->validate('', new ValidTimezone());

    $this->assertNoViolation();
  }

  #[Test]
  public function testANonStringValueIsIgnored(): void
  {
    $this->validator->validate(42, new ValidTimezone());

    $this->assertNoViolation();
  }

  #[Test]
  public function testAnIanaIdentifierIsValid(): void
  {
    $this->validator->validate('Europe/Paris', new ValidTimezone());

    $this->assertNoViolation();
  }

  #[Test]
  public function testAnUnknownIdentifierRaisesAViolation(): void
  {
    $constraint = new ValidTimezone();

    $this->validator->validate('Mars/Olympus_Mons', $constraint);

    $this->buildViolation($constraint->message)
      ->setParameter('{{ timezone }}', 'Mars/Olympus_Mons')
      ->assertRaised();
  }

  #[Test]
  public function testAForeignConstraintIsRejected(): void
  {
    $this->expectException(UnexpectedTypeException::class);

    $this->validator->validate('Europe/Paris', new NotBlank());
  }

  protected function createValidator(): ValidTimezoneValidator
  {
    return new ValidTimezoneValidator();
  }
  // #endregion
}
