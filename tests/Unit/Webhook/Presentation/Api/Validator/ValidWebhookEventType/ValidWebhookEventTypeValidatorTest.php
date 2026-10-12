<?php

declare(strict_types=1);

namespace Tests\Unit\Webhook\Presentation\Api\Validator\ValidWebhookEventType;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;
use Webhook\Application\Contract\Event\WebhookEventDescriptor;
use Webhook\Application\Port\Outbound\WebhookEventSourcePort;
use Webhook\Application\Service\WebhookEventCatalogRegistry;
use Webhook\Presentation\Api\Validator\ValidWebhookEventType\{ValidWebhookEventType, ValidWebhookEventTypeValidator};

/**
 * Class ValidWebhookEventTypeValidatorTest
 *
 * @category Test
 */
#[CoversClass(ValidWebhookEventTypeValidator::class)]
final class ValidWebhookEventTypeValidatorTest extends TestCase
{
  #[Test]
  public function acceptsAnAdditionalInstalledContributor(): void
  {
    $source = $this->createStub(WebhookEventSourcePort::class);
    $source->method('descriptors')->willReturn([
      new WebhookEventDescriptor('invoice.issued', 'Invoice issued', 'invoice.issued_event'),
    ]);
    $context = $this->createMock(ExecutionContextInterface::class);
    $context->expects(self::never())->method('buildViolation');
    $validator = new ValidWebhookEventTypeValidator(new WebhookEventCatalogRegistry([$source]));
    $validator->initialize($context);

    $validator->validate('invoice.issued', new ValidWebhookEventType());
  }

  #[Test]
  public function deniesUnregisteredAndReservedSelections(): void
  {
    $builder = $this->createMock(ConstraintViolationBuilderInterface::class);
    $builder->expects(self::exactly(3))->method('addViolation');
    $context = $this->createMock(ExecutionContextInterface::class);
    $context->expects(self::exactly(3))->method('buildViolation')->willReturn($builder);
    $validator = new ValidWebhookEventTypeValidator(new WebhookEventCatalogRegistry([]));
    $validator->initialize($context);

    $validator->validate('invoice.issued', new ValidWebhookEventType());
    $validator->validate('webhook.ping', new ValidWebhookEventType());
    $validator->validate(null, new ValidWebhookEventType());
  }

  #[Test]
  public function rejectsAnUnrelatedConstraint(): void
  {
    $this->expectException(UnexpectedTypeException::class);
    new ValidWebhookEventTypeValidator(new WebhookEventCatalogRegistry([]))->validate('invoice.issued', new NotBlank());
  }
}
