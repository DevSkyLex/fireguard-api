<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Presentation\Api\Validator\ValidScopes;

use OAuth\Presentation\Api\Validator\ValidScopes\{ValidScopes, ValidScopesValidator};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

/**
 * Test ValidScopesValidatorTest.
 *
 * @category Validator Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: ValidScopesValidator::class)]
final class ValidScopesValidatorTest extends TestCase
{
  // #region Methods
  /**
   * Method testValidScopesPasses.
   *
   * Test that valid scopes pass validation.
   */
  #[Test]
  public function testValidScopesPasses(): void
  {
    $context = $this->createMock(ExecutionContextInterface::class);
    $context->expects(self::never())->method('buildViolation');

    $validator = new ValidScopesValidator();
    $validator->initialize(context: $context);

    $constraint = new ValidScopes();
    $validator->validate(value: ['openid', 'profile', 'email'], constraint: $constraint);
  }

  /**
   * Method testInvalidScopeFails.
   *
   * Test that invalid scope fails validation.
   */
  #[Test]
  public function testInvalidScopeFails(): void
  {
    $violationBuilder = $this->createMock(ConstraintViolationBuilderInterface::class);
    $violationBuilder->expects(self::once())
      ->method('setParameter')
      ->willReturnSelf();
    $violationBuilder->expects(self::once())
      ->method('addViolation');

    $context = $this->createMock(ExecutionContextInterface::class);
    $context->expects(self::once())
      ->method('buildViolation')
      ->willReturn($violationBuilder);

    $validator = new ValidScopesValidator();
    $validator->initialize(context: $context);

    $constraint = new ValidScopes();
    $validator->validate(value: ['openid', 'invalid_scope'], constraint: $constraint);
  }

  /**
   * Method testCustomAllowedScopes.
   *
   * Test that custom allowed scopes can be specified.
   */
  #[Test]
  public function testCustomAllowedScopes(): void
  {
    $context = $this->createMock(ExecutionContextInterface::class);
    $context->expects(self::never())->method('buildViolation');

    $validator = new ValidScopesValidator();
    $validator->initialize(context: $context);

    $constraint = new ValidScopes(allowedScopes: ['custom', 'scopes']);
    $validator->validate(value: ['custom', 'scopes'], constraint: $constraint);
  }

  /**
   * Method testNullValuePasses.
   *
   * Test that null value passes (nullable allowed).
   */
  #[Test]
  public function testNullValuePasses(): void
  {
    $context = $this->createMock(ExecutionContextInterface::class);
    $context->expects(self::never())->method('buildViolation');

    $validator = new ValidScopesValidator();
    $validator->initialize(context: $context);

    $constraint = new ValidScopes();
    $validator->validate(value: null, constraint: $constraint);
  }

  #[Test]
  public function testScalarValueIsWrapped(): void
  {
    $context = $this->createMock(ExecutionContextInterface::class);
    $context->expects(self::never())->method('buildViolation');

    $validator = new ValidScopesValidator();
    $validator->initialize(context: $context);

    $constraint = new ValidScopes();
    $validator->validate(value: 'openid', constraint: $constraint);
  }

  #[Test]
  public function testNonStringValuesAreIgnored(): void
  {
    $context = $this->createMock(ExecutionContextInterface::class);
    $context->expects(self::never())->method('buildViolation');

    $validator = new ValidScopesValidator();
    $validator->initialize(context: $context);

    $constraint = new ValidScopes();
    $validator->validate(value: [123, null], constraint: $constraint);
  }

  #[Test]
  public function testUnexpectedConstraintThrows(): void
  {
    $validator = new ValidScopesValidator();

    $this->expectException(UnexpectedTypeException::class);

    $validator->validate(value: 'openid', constraint: $this->createStub(\Symfony\Component\Validator\Constraint::class));
  }
  // #endregion
}
