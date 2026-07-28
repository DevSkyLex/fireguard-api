<?php

declare(strict_types=1);

namespace Tests\Unit\Webhook\Presentation\Api\Validator\ValidWebhookUrl;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;
use Webhook\Domain\Service\WebhookUrlPolicy;
use Webhook\Presentation\Api\Validator\ValidWebhookUrl\{ValidWebhookUrl, ValidWebhookUrlValidator};

/**
 * Test ValidWebhookUrlValidatorTest.
 *
 * @category Validator Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ValidWebhookUrlValidator::class)]
final class ValidWebhookUrlValidatorTest extends TestCase
{
  #[Test]
  public function testItRejectsAnUnrelatedConstraint(): void
  {
    $validator = new ValidWebhookUrlValidator(new WebhookUrlPolicy());

    $this->expectException(UnexpectedTypeException::class);

    $validator->validate('https://example.com/hook', new NotBlank());
  }

  #[Test]
  public function testItAddsNoViolationForAPublicHttpsUrl(): void
  {
    $context = $this->createMock(ExecutionContextInterface::class);
    $context->expects(self::never())->method('buildViolation');

    $validator = new ValidWebhookUrlValidator(new WebhookUrlPolicy());
    $validator->initialize($context);

    $validator->validate('https://example.com/hook', new ValidWebhookUrl());
  }

  #[Test]
  public function testItSkipsNullAndEmptyAndNonStringValues(): void
  {
    $context = $this->createMock(ExecutionContextInterface::class);
    $context->expects(self::never())->method('buildViolation');

    $validator = new ValidWebhookUrlValidator(new WebhookUrlPolicy());
    $validator->initialize($context);

    $validator->validate(null, new ValidWebhookUrl());
    $validator->validate('', new ValidWebhookUrl());
    $validator->validate(42, new ValidWebhookUrl());
  }

  #[Test]
  public function testItAddsAViolationForARejectedUrl(): void
  {
    $constraint = new ValidWebhookUrl();

    $builder = $this->createMock(ConstraintViolationBuilderInterface::class);
    $builder->expects(self::once())
      ->method('setParameter')
      ->with('{{ value }}', 'http://127.0.0.1/hook')
      ->willReturnSelf();
    $builder->expects(self::once())->method('addViolation');

    $context = $this->createMock(ExecutionContextInterface::class);
    $context->expects(self::once())
      ->method('buildViolation')
      ->with($constraint->message)
      ->willReturn($builder);

    $validator = new ValidWebhookUrlValidator(new WebhookUrlPolicy());
    $validator->initialize($context);

    $validator->validate('http://127.0.0.1/hook', $constraint);
  }
}
