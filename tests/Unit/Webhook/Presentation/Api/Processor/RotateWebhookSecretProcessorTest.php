<?php

declare(strict_types=1);

namespace Tests\Unit\Webhook\Presentation\Api\Processor;

use ApiPlatform\Metadata\Post;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Organization\Domain\Exception\OrganizationAccessDeniedException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};
use Webhook\Application\UseCase\Command\Subscription\RotateWebhookSecret\{RotateWebhookSecretCommand, RotateWebhookSecretResult};
use Webhook\Presentation\Api\Factory\WebhookSubscriptionOutputFactory;
use Webhook\Presentation\Api\Processor\RotateWebhookSecretProcessor;

/**
 * Test RotateWebhookSecretProcessorTest.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(RotateWebhookSecretProcessor::class)]
final class RotateWebhookSecretProcessorTest extends TestCase
{
  private const string ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a01';

  private const string WEBHOOK_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a02';

  private const string USER_ID = 'user-id';

  #[Test]
  public function testProcessDispatchesTheCommandAndRevealsTheNewSecret(): void
  {
    $captured = null;
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willReturnCallback(static function (RotateWebhookSecretCommand $command) use (&$captured): RotateWebhookSecretResult {
        $captured = $command;

        return new RotateWebhookSecretResult(
          id: self::WEBHOOK_ID,
          organizationId: self::ORGANIZATION_ID,
          url: 'https://example.com/hook',
          eventTypes: ['intervention.published'],
          isActive: true,
          description: 'Integration',
          secret: 'whsec_rotated',
          createdAt: new DateTimeImmutable('2026-07-18T00:00:00+00:00'),
          updatedAt: new DateTimeImmutable('2026-07-18T01:00:00+00:00'),
        );
      });

    $processor = new RotateWebhookSecretProcessor(
      $commandBus,
      $this->authenticatedSecurity(),
      new WebhookSubscriptionOutputFactory(),
    );

    $output = $processor->process(null, new Post(), [
      'organizationId' => self::ORGANIZATION_ID,
      'webhookId' => self::WEBHOOK_ID,
    ]);

    self::assertInstanceOf(RotateWebhookSecretCommand::class, $captured);
    self::assertSame(self::ORGANIZATION_ID, $captured->organizationId);
    self::assertSame(self::USER_ID, $captured->actorUserId);
    self::assertSame(self::WEBHOOK_ID, $captured->subscriptionId);

    self::assertSame(self::WEBHOOK_ID, $output->id);
    self::assertSame('whsec_rotated', $output->secret);
    self::assertSame('2026-07-18T01:00:00+00:00', $output->updatedAt);
  }

  #[Test]
  public function testProcessRequiresAuthentication(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $processor = new RotateWebhookSecretProcessor(
      $this->createStub(CommandBusPort::class),
      $security,
      new WebhookSubscriptionOutputFactory(),
    );

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(null, new Post(), [
      'organizationId' => self::ORGANIZATION_ID,
      'webhookId' => self::WEBHOOK_ID,
    ]);
  }

  #[Test]
  public function testProcessRequiresBothUriVariables(): void
  {
    $processor = new RotateWebhookSecretProcessor(
      $this->createStub(CommandBusPort::class),
      $this->authenticatedSecurity(),
      new WebhookSubscriptionOutputFactory(),
    );

    $this->expectException(BadRequestHttpException::class);

    $processor->process(null, new Post(), ['organizationId' => self::ORGANIZATION_ID, 'webhookId' => '']);
  }

  #[Test]
  public function testProcessMapsAnOrganizationDenialToAccessDenied(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(
      OrganizationAccessDeniedException::missingPermission('organization.settings.write'),
    );

    $processor = new RotateWebhookSecretProcessor(
      $commandBus,
      $this->authenticatedSecurity(),
      new WebhookSubscriptionOutputFactory(),
    );

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(null, new Post(), [
      'organizationId' => self::ORGANIZATION_ID,
      'webhookId' => self::WEBHOOK_ID,
    ]);
  }

  /**
   * Method authenticatedSecurity.
   *
   * @return Security a security stub returning an authenticated user
   */
  private function authenticatedSecurity(): Security
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(
      new SecurityUser(self::USER_ID, 'user@example.com', 'password', ['ROLE_USER'], [], true),
    );

    return $security;
  }
}
