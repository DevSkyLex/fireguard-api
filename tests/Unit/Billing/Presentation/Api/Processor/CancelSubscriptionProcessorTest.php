<?php

declare(strict_types=1);

namespace Tests\Unit\Billing\Presentation\Api\Processor;

use ApiPlatform\Metadata\Post;
use Auth\Infrastructure\Security\User\SecurityUser;
use Billing\Application\Port\Outbound\OrganizationAccessPort;
use Billing\Application\UseCase\Command\CancelSubscription\CancelSubscriptionCommand;
use Billing\Application\UseCase\Query\GetOrganizationSubscription\GetOrganizationSubscriptionResult;
use Billing\Presentation\Api\Processor\CancelSubscriptionProcessor;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Message\ResultMessage;
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};

/**
 * Test CancelSubscriptionProcessorTest.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(CancelSubscriptionProcessor::class)]
final class CancelSubscriptionProcessorTest extends TestCase
{
  private const string ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64b01';

  private const string USER_ID = 'user-id';

  #[Test]
  public function testProcessSchedulesTheCancellationAndReturnsTheRefreshedState(): void
  {
    $captured = null;
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willReturnCallback(function (CancelSubscriptionCommand $command) use (&$captured): ResultMessage {
        $captured = $command;

        return $this->createStub(ResultMessage::class);
      });

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willReturn(new GetOrganizationSubscriptionResult(
      organizationId: self::ORGANIZATION_ID,
      hasSubscription: true,
      active: true,
      status: 'active',
      planKey: 'pro',
      planName: 'Pro',
      interval: 'month',
      currentPeriodEnd: new DateTimeImmutable('2026-08-18T00:00:00+00:00'),
      cancelAtPeriodEnd: true,
    ));

    $processor = new CancelSubscriptionProcessor(
      $commandBus,
      $queryBus,
      $this->access(true),
      $this->authenticatedSecurity(),
    );

    $output = $processor->process(null, new Post(), ['organizationId' => self::ORGANIZATION_ID]);

    self::assertInstanceOf(CancelSubscriptionCommand::class, $captured);
    self::assertSame(self::ORGANIZATION_ID, $captured->organizationId);

    self::assertSame(self::ORGANIZATION_ID, $output->organizationId);
    self::assertTrue($output->hasSubscription);
    self::assertTrue($output->active);
    self::assertSame('active', $output->status);
    self::assertSame('pro', $output->planKey);
    self::assertSame('month', $output->interval);
    self::assertSame('2026-08-18T00:00:00+00:00', $output->currentPeriodEnd);
    self::assertTrue($output->cancelAtPeriodEnd);
  }

  #[Test]
  public function testProcessRequiresAuthentication(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $processor = new CancelSubscriptionProcessor(
      $this->createStub(CommandBusPort::class),
      $this->createStub(QueryBusPort::class),
      $this->access(true),
      $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(null, new Post(), ['organizationId' => self::ORGANIZATION_ID]);
  }

  #[Test]
  public function testProcessRequiresAnOrganizationIdUriVariable(): void
  {
    $processor = new CancelSubscriptionProcessor(
      $this->createStub(CommandBusPort::class),
      $this->createStub(QueryBusPort::class),
      $this->access(true),
      $this->authenticatedSecurity(),
    );

    $this->expectException(BadRequestHttpException::class);

    $processor->process(null, new Post(), []);
  }

  #[Test]
  public function testProcessRequiresTheSettingsWritePermission(): void
  {
    $processor = new CancelSubscriptionProcessor(
      $this->createStub(CommandBusPort::class),
      $this->createStub(QueryBusPort::class),
      $this->access(false),
      $this->authenticatedSecurity(),
    );

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(null, new Post(), ['organizationId' => self::ORGANIZATION_ID]);
  }

  #[Test]
  public function testProcessRethrowsUnrelatedMessengerFailures(): void
  {
    $failure = MessengerRuntimeException::wrap(new RuntimeException('Gateway down.'));

    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException($failure);

    $processor = new CancelSubscriptionProcessor(
      $commandBus,
      $this->createStub(QueryBusPort::class),
      $this->access(true),
      $this->authenticatedSecurity(),
    );

    $this->expectException(MessengerRuntimeException::class);

    $processor->process(null, new Post(), ['organizationId' => self::ORGANIZATION_ID]);
  }

  /**
   * Method access.
   *
   * @param bool $granted whether the permission check succeeds
   *
   * @return OrganizationAccessPort the access port stub
   */
  private function access(bool $granted): OrganizationAccessPort
  {
    $access = $this->createStub(OrganizationAccessPort::class);
    $access->method('hasPermission')->willReturn($granted);

    return $access;
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
