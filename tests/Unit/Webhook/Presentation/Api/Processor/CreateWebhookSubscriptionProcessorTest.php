<?php

declare(strict_types=1);

namespace Tests\Unit\Webhook\Presentation\Api\Processor;

use ApiPlatform\Metadata\Post;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};
use Webhook\Application\UseCase\Command\Subscription\CreateWebhookSubscription\{CreateWebhookSubscriptionCommand, CreateWebhookSubscriptionResult};
use Webhook\Presentation\Api\Dto\Input\CreateWebhookSubscriptionInput;
use Webhook\Presentation\Api\Dto\Output\WebhookSecretOutput;
use Webhook\Presentation\Api\Factory\WebhookSubscriptionOutputFactory;
use Webhook\Presentation\Api\Processor\CreateWebhookSubscriptionProcessor;

/**
 * Test CreateWebhookSubscriptionProcessorTest.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(CreateWebhookSubscriptionProcessor::class)]
final class CreateWebhookSubscriptionProcessorTest extends TestCase
{
  private const string ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a01';

  private const string USER_ID = 'user-id';

  #[Test]
  public function testProcessDispatchesTheCommandAndReturnsTheSecretOutput(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(
      new SecurityUser(self::USER_ID, 'user@example.com', 'password', ['ROLE_USER'], [], true),
    );

    $input = new CreateWebhookSubscriptionInput();
    $input->url = 'https://example.com/hook';
    $input->eventTypes = ['intervention.published'];
    $input->description = 'Integration';

    $captured = null;
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willReturnCallback(function (CreateWebhookSubscriptionCommand $command) use (&$captured): CreateWebhookSubscriptionResult {
        $captured = $command;

        return new CreateWebhookSubscriptionResult(
          id: 'sub-1',
          organizationId: self::ORGANIZATION_ID,
          url: 'https://example.com/hook',
          eventTypes: ['intervention.published'],
          isActive: true,
          description: 'Integration',
          secret: 'whsec_abcdef',
          createdAt: new DateTimeImmutable('2026-07-18T00:00:00+00:00'),
          updatedAt: new DateTimeImmutable('2026-07-18T00:00:00+00:00'),
        );
      });

    $processor = new CreateWebhookSubscriptionProcessor($commandBus, $security, new WebhookSubscriptionOutputFactory());

    $output = $processor->process($input, new Post(), ['organizationId' => self::ORGANIZATION_ID]);

    self::assertInstanceOf(CreateWebhookSubscriptionCommand::class, $captured);
    self::assertSame(self::USER_ID, $captured->actorUserId);
    self::assertSame(self::ORGANIZATION_ID, $captured->organizationId);
    self::assertSame('https://example.com/hook', $captured->url);
    self::assertSame(['intervention.published'], $captured->eventTypes);

    self::assertInstanceOf(WebhookSecretOutput::class, $output);
    self::assertSame('sub-1', $output->id);
    self::assertSame('whsec_abcdef', $output->secret);
  }

  #[Test]
  public function testProcessRequiresAuthentication(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $processor = new CreateWebhookSubscriptionProcessor(
      $this->createStub(CommandBusPort::class),
      $security,
      new WebhookSubscriptionOutputFactory(),
    );

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(new CreateWebhookSubscriptionInput(), new Post(), ['organizationId' => self::ORGANIZATION_ID]);
  }

  #[Test]
  public function testProcessRequiresAnOrganizationIdUriVariable(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(
      new SecurityUser(self::USER_ID, 'user@example.com', 'password', ['ROLE_USER'], [], true),
    );

    $processor = new CreateWebhookSubscriptionProcessor(
      $this->createStub(CommandBusPort::class),
      $security,
      new WebhookSubscriptionOutputFactory(),
    );

    $this->expectException(BadRequestHttpException::class);

    $processor->process(new CreateWebhookSubscriptionInput(), new Post(), []);
  }
}
