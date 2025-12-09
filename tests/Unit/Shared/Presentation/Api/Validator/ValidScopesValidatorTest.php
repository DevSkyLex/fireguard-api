<?php

declare(strict_types=1);

namespace Tests\Shared\Presentation\Api\Validator;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Shared\Presentation\Api\Validator\ValidScopes;
use Shared\Presentation\Api\Validator\ValidScopesValidator;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

/**
 * Test ValidScopesValidatorTest
 * @final
 *
 * Test class for ValidScopesValidator.
 *
 * @category Validator Tests
 * @package Tests\Shared\Presentation\Api\Validator
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: ValidScopesValidator::class)]
final class ValidScopesValidatorTest extends TestCase
{
  //#region Methods
  /**
   * Method testValidScopesPasses
   *
   * Test that valid scopes pass validation.
   *
   * @access public
   *
   * @return void
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
   * Method testInvalidScopeFails
   *
   * Test that invalid scope fails validation.
   *
   * @access public
   *
   * @return void
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
   * Method testCustomAllowedScopes
   *
   * Test that custom allowed scopes can be specified.
   *
   * @access public
   *
   * @return void
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
   * Method testNullValuePasses
   *
   * Test that null value passes (nullable allowed).
   *
   * @access public
   *
   * @return void
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
  //#endregion
}
