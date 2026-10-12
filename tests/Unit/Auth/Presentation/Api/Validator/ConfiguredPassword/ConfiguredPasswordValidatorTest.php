<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Presentation\Api\Validator\ConfiguredPassword;

use Auth\Presentation\Api\Validator\ConfiguredPassword\{ConfiguredPassword, ConfiguredPasswordValidator};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\{Constraint, ConstraintValidatorFactoryInterface, ConstraintValidatorInterface, Validation};
use User\Application\Port\Outbound\PasswordPolicyPort;
use User\Infrastructure\Adapter\Policy\ConfiguredPasswordPolicyAdapter;

/**
 * Class ConfiguredPasswordValidatorTest
 *
 * Tests actual default and alternate policy messages without HTTP or service-container wiring.
 *
 * @category Validator Tests
 */
#[CoversClass(ConfiguredPasswordValidator::class)]
final class ConfiguredPasswordValidatorTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testDefaultComplexityMessageRemainsStable(): void
  {
    $violations = $this->validate('abcdefgh', new ConfiguredPasswordPolicyAdapter());
    self::assertSame(['Password must contain at least one uppercase letter, one lowercase letter, one digit, and one special character.'], $violations);
  }

  #[Test]
  public function testCustomComplexityMessageIncludesOnlyActualRequirements(): void
  {
    $violations = $this->validate('abcdefgh', new ConfiguredPasswordPolicyAdapter(4, 12, false, false, true, ''));
    self::assertSame(['Password must contain: a digit.'], $violations);
  }

  #[Test]
  public function testCustomLengthLimitsAndDisabledComplexityAreEnforced(): void
  {
    $policy = new ConfiguredPasswordPolicyAdapter(4, 12, false, false, false, '');
    self::assertSame([], $this->validate('abcd', $policy));
    self::assertSame(['Password must be at least 4 characters long.'], $this->validate('abc', $policy));
  }

  /**
   * Method validate
   *
   * @access private
   *
   * @param string $password proposed credential
   * @param PasswordPolicyPort $policy configured owner rules
   *
   * @return list<string> exact rendered messages
   */
  private function validate(string $password, PasswordPolicyPort $policy): array
  {
    $factory = new class (new ConfiguredPasswordValidator($policy)) implements ConstraintValidatorFactoryInterface {
      public function __construct(private ConstraintValidatorInterface $validator)
      {
      }

      public function getInstance(Constraint $constraint): ConstraintValidatorInterface
      {
        return $this->validator;
      }
    };
    $validator = Validation::createValidatorBuilder()->setConstraintValidatorFactory($factory)->getValidator();
    $messages = [];
    foreach ($validator->validate($password, new ConfiguredPassword()) as $violation) {
      $messages[] = (string) $violation->getMessage();
    }

    return $messages;
  }
  // #endregion
}
