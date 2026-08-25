<?php

declare(strict_types=1);

namespace Tests\Unit\Billing\Presentation\Api\Processor;

use ApiPlatform\Metadata\Post;
use Auth\Infrastructure\Security\User\SecurityUser;
use Billing\Application\Port\Outbound\OrganizationAccessPort;
use Billing\Application\UseCase\Command\StartPortal\{StartPortalCommand, StartPortalResult};
use Billing\Presentation\Api\Processor\StartPortalProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};

/**
 * Test StartPortalProcessorTest.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(StartPortalProcessor::class)]
final class StartPortalProcessorTest extends TestCase
{
  private const string ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64b01';

  private const string USER_ID = 'user-id';

  #[Test]
  public function testProcessOpensAPortalSession(): void
  {
    $captured = null;
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willReturnCallback(static function (StartPortalCommand $command) use (&$captured): StartPortalResult {
        $captured = $command;

        return new StartPortalResult('https://billing.stripe.com/session/abc');
      });

    $processor = new StartPortalProcessor($commandBus, $this->access(true), $this->authenticatedSecurity());

    $output = $processor->process(null, new Post(), ['organizationId' => self::ORGANIZATION_ID]);

    self::assertInstanceOf(StartPortalCommand::class, $captured);
    self::assertSame(self::ORGANIZATION_ID, $captured->organizationId);

    self::assertSame(self::ORGANIZATION_ID, $output->organizationId);
    self::assertSame('https://billing.stripe.com/session/abc', $output->url);
  }

  #[Test]
  public function testProcessRequiresAuthentication(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $processor = new StartPortalProcessor($this->createStub(CommandBusPort::class), $this->access(true), $security);

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(null, new Post(), ['organizationId' => self::ORGANIZATION_ID]);
  }

  #[Test]
  public function testProcessRequiresAnOrganizationIdUriVariable(): void
  {
    $processor = new StartPortalProcessor(
      $this->createStub(CommandBusPort::class),
      $this->access(true),
      $this->authenticatedSecurity(),
    );

    $this->expectException(BadRequestHttpException::class);

    $processor->process(null, new Post(), []);
  }

  #[Test]
  public function testProcessRequiresTheSettingsWritePermission(): void
  {
    $processor = new StartPortalProcessor(
      $this->createStub(CommandBusPort::class),
      $this->access(false),
      $this->authenticatedSecurity(),
    );

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(null, new Post(), ['organizationId' => self::ORGANIZATION_ID]);
  }

  #[Test]
  public function testProcessRethrowsUnrelatedMessengerFailures(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(
      MessengerRuntimeException::wrap(new RuntimeException('Gateway down.')),
    );

    $processor = new StartPortalProcessor($commandBus, $this->access(true), $this->authenticatedSecurity());

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
