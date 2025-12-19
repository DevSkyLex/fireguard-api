<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Presentation\Api\Validator;

use OAuth\Presentation\Api\Validator\ValidRedirectUri\ValidRedirectUri;
use OAuth\Presentation\Api\Validator\ValidRedirectUri\ValidRedirectUriValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

/**
 * Test ValidRedirectUriValidatorTest.
 *
 * @category Validator Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: ValidRedirectUriValidator::class)]
final class ValidRedirectUriValidatorTest extends TestCase
{
    // #region Methods
    /**
     * Method testValidHttpsUri.
     *
     * Test that valid HTTPS URIs pass.
     */
    #[Test]
    public function testValidHttpsUri(): void
    {
        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects(self::never())->method('buildViolation');

        $validator = new ValidRedirectUriValidator();
        $validator->initialize(context: $context);

        $constraint = new ValidRedirectUri();
        $validator->validate(value: 'https://example.com/callback', constraint: $constraint);
    }

    /**
     * Method testValidLocalhostHttpUri.
     *
     * Test that localhost with HTTP is allowed.
     */
    #[Test]
    public function testValidLocalhostHttpUri(): void
    {
        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects(self::never())->method('buildViolation');

        $validator = new ValidRedirectUriValidator();
        $validator->initialize(context: $context);

        $constraint = new ValidRedirectUri();
        $validator->validate(value: 'http://localhost:3000/callback', constraint: $constraint);
    }

    /**
     * Method testInvalidHttpUri.
     *
     * Test that HTTP on non-localhost fails.
     */
    #[Test]
    public function testInvalidHttpUri(): void
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

        $validator = new ValidRedirectUriValidator();
        $validator->initialize(context: $context);

        $constraint = new ValidRedirectUri();
        $validator->validate(value: 'http://example.com/callback', constraint: $constraint);
    }

    /**
     * Method testUriWithFragmentFails.
     *
     * Test that URIs with fragments fail.
     */
    #[Test]
    public function testUriWithFragmentFails(): void
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

        $validator = new ValidRedirectUriValidator();
        $validator->initialize(context: $context);

        $constraint = new ValidRedirectUri();
        $validator->validate(value: 'https://example.com/callback#fragment', constraint: $constraint);
    }

    /**
     * Method testNullValuePasses.
     *
     * Test that null value passes (should be handled by NotBlank).
     */
    #[Test]
    public function testNullValuePasses(): void
    {
        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects(self::never())->method('buildViolation');

        $validator = new ValidRedirectUriValidator();
        $validator->initialize(context: $context);

        $constraint = new ValidRedirectUri();
        $validator->validate(value: null, constraint: $constraint);
    }
    // #endregion
}
