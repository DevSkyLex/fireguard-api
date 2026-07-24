<?php

declare(strict_types=1);

namespace Tests\Unit\Assistant\Presentation\Api\Validator\ValidAssistantModel;

use Assistant\Domain\Service\AssistantModelPolicy;
use Assistant\Presentation\Api\Validator\ValidAssistantModel\{ValidAssistantModel, ValidAssistantModelValidator};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * Test ValidAssistantModelValidatorTest.
 *
 * Confirms a model outside the configured `OLLAMA_ALLOWED_MODELS` allowlist
 * is rejected at the API boundary.
 *
 * @category Validator Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @extends ConstraintValidatorTestCase<ValidAssistantModelValidator>
 */
#[CoversClass(ValidAssistantModelValidator::class)]
final class ValidAssistantModelValidatorTest extends ConstraintValidatorTestCase
{
  #[Test]
  public function testNullIsValid(): void
  {
    $this->validator->validate(null, new ValidAssistantModel());

    $this->assertNoViolation();
  }

  #[Test]
  public function testBlankIsValid(): void
  {
    $this->validator->validate('', new ValidAssistantModel());

    $this->assertNoViolation();
  }

  #[Test]
  public function testAnAllowedModelIsValid(): void
  {
    $this->validator->validate('llama3', new ValidAssistantModel());

    $this->assertNoViolation();
  }

  #[Test]
  public function testAModelOutsideTheAllowlistRaisesAViolation(): void
  {
    $constraint = new ValidAssistantModel();

    $this->validator->validate('gpt-4', $constraint);

    $this->buildViolation($constraint->message)
      ->setParameter('{{ value }}', 'gpt-4')
      ->assertRaised();
  }

  protected function createValidator(): ValidAssistantModelValidator
  {
    return new ValidAssistantModelValidator(new AssistantModelPolicy(['llama3', 'mistral']));
  }
}
