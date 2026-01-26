<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Presentation\Api\Validator\GrantTypeRequirements;

use OAuth\Presentation\Api\Dto\Input\Token\TokenInput;
use OAuth\Presentation\Api\Validator\GrantTypeRequirements\{GrantTypeRequirements, GrantTypeRequirementsValidator};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Exception\{UnexpectedTypeException, UnexpectedValueException};
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

/**
 * Test GrantTypeRequirementsValidatorTest.
 *
 * @category Validator Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: GrantTypeRequirementsValidator::class)]
final class GrantTypeRequirementsValidatorTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testRefreshTokenGrantPassesWhenTokenPresent(): void
  {
    $context = $this->createMock(ExecutionContextInterface::class);
    $context->expects(self::never())->method('buildViolation');

    $validator = new GrantTypeRequirementsValidator();
    $validator->initialize(context: $context);

    $input = new TokenInput();
    $input->grantType = 'refresh_token';
    $input->refreshToken = 'refresh-token';

    $validator->validate(value: $input, constraint: new GrantTypeRequirements());
  }

  #[Test]
  public function testRefreshTokenGrantAddsViolationWhenMissing(): void
  {
    $builder = $this->createMock(ConstraintViolationBuilderInterface::class);
    $builder->expects(self::once())
      ->method('atPath')
      ->with('refreshToken')
      ->willReturnSelf();
    $builder->expects(self::once())->method('addViolation');

    $context = $this->createMock(ExecutionContextInterface::class);
    $context->expects(self::once())
      ->method('buildViolation')
      ->with(GrantTypeRequirements::MESSAGE_REFRESH_TOKEN_REQUIRED)
      ->willReturn($builder);

    $validator = new GrantTypeRequirementsValidator();
    $validator->initialize(context: $context);

    $input = new TokenInput();
    $input->grantType = 'refresh_token';

    $validator->validate(value: $input, constraint: new GrantTypeRequirements());
  }

  #[Test]
  public function testAuthorizationCodeGrantAddsViolations(): void
  {
    $builder = $this->createMock(ConstraintViolationBuilderInterface::class);
    $builder->expects(self::exactly(3))
      ->method('atPath')
      ->willReturnSelf();
    $builder->expects(self::exactly(3))->method('addViolation');

    $context = $this->createMock(ExecutionContextInterface::class);
    $context->expects(self::exactly(3))
      ->method('buildViolation')
      ->willReturn($builder);

    $validator = new GrantTypeRequirementsValidator();
    $validator->initialize(context: $context);

    $input = new TokenInput();
    $input->grantType = 'authorization_code';

    $validator->validate(value: $input, constraint: new GrantTypeRequirements());
  }

  #[Test]
  public function testNullValuePasses(): void
  {
    $context = $this->createMock(ExecutionContextInterface::class);
    $context->expects(self::never())->method('buildViolation');

    $validator = new GrantTypeRequirementsValidator();
    $validator->initialize(context: $context);

    $validator->validate(value: null, constraint: new GrantTypeRequirements());
  }

  #[Test]
  public function testThrowsOnInvalidConstraint(): void
  {
    $validator = new GrantTypeRequirementsValidator();
    $validator->initialize(context: $this->createMock(ExecutionContextInterface::class));

    $this->expectException(UnexpectedTypeException::class);

    $validator->validate(value: null, constraint: $this->createMock(Constraint::class));
  }

  #[Test]
  public function testThrowsOnInvalidValueType(): void
  {
    $validator = new GrantTypeRequirementsValidator();
    $validator->initialize(context: $this->createMock(ExecutionContextInterface::class));

    $this->expectException(UnexpectedValueException::class);

    $validator->validate(value: 'invalid', constraint: new GrantTypeRequirements());
  }
  // #endregion
}
