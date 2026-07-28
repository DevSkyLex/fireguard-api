<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Presentation\Api\Validator\ValidDuration;

use Intervention\Presentation\Api\Validator\ValidDuration\{ValidDuration, ValidDurationValidator};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * Test ValidDurationValidatorTest.
 *
 * @category Validator Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @extends ConstraintValidatorTestCase<ValidDurationValidator>
 */
#[CoversClass(ValidDurationValidator::class)]
final class ValidDurationValidatorTest extends ConstraintValidatorTestCase
{
  // #region Methods
  #[Test]
  public function testNullIsValid(): void
  {
    $this->validator->validate(null, new ValidDuration());

    $this->assertNoViolation();
  }

  #[Test]
  public function testBlankIsValid(): void
  {
    $this->validator->validate('', new ValidDuration());

    $this->assertNoViolation();
  }

  #[Test]
  public function testANonStringValueIsIgnored(): void
  {
    $this->validator->validate(14, new ValidDuration());

    $this->assertNoViolation();
  }

  #[Test]
  public function testAnIsoDurationIsValid(): void
  {
    $this->validator->validate('P14D', new ValidDuration());

    $this->assertNoViolation();
  }

  #[Test]
  public function testAMalformedDurationRaisesAViolation(): void
  {
    $constraint = new ValidDuration();

    $this->validator->validate('14 days', $constraint);

    $this->buildViolation($constraint->message)
      ->setParameter('{{ duration }}', '14 days')
      ->assertRaised();
  }

  #[Test]
  public function testAForeignConstraintIsRejected(): void
  {
    $this->expectException(UnexpectedTypeException::class);

    $this->validator->validate('P14D', new NotBlank());
  }

  protected function createValidator(): ValidDurationValidator
  {
    return new ValidDurationValidator();
  }
  // #endregion
}
