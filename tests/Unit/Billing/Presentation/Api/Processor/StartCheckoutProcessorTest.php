<?php

declare(strict_types=1);

namespace Tests\Unit\Billing\Presentation\Api\Processor;

use ApiPlatform\Metadata\Post;
use Auth\Infrastructure\Security\User\SecurityUser;
use Billing\Application\Port\Outbound\OrganizationAccessPort;
use Billing\Application\UseCase\Command\StartCheckout\{StartCheckoutCommand, StartCheckoutResult};
use Billing\Presentation\Api\Dto\Input\StartCheckoutInput;
use Billing\Presentation\Api\Dto\Output\CheckoutSessionOutput;
use Billing\Presentation\Api\Processor\StartCheckoutProcessor;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};

/**
 * Test StartCheckoutProcessorTest.
 *
 * Opening a Checkout session is a paid-plan write, so the processor owns
 * three gates before it ever reaches the command bus: authentication, an
 * organization in the URI, and `organization.settings.write`. Each has to
 * hold on its own.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(StartCheckoutProcessor::class)]
final class StartCheckoutProcessorTest extends TestCase
{
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655449001';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655449002';

  #[Test]
  public function testItReturnsTheHostedCheckoutUrl(): void
  {
    $captured = null;

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willReturnCallback(static function (StartCheckoutCommand $command) use (&$captured): StartCheckoutResult {
        $captured = $command;

        return new StartCheckoutResult('https://checkout.stripe.com/c/pay/cs_test_123');
      });

    $output = $this->createProcessor($commandBus)
      ->process($this->input(), new Post(), ['organizationId' => self::ORGANIZATION_ID]);

    self::assertInstanceOf(CheckoutSessionOutput::class, $output);
    self::assertSame(self::ORGANIZATION_ID, $output->organizationId);
    self::assertSame('https://checkout.stripe.com/c/pay/cs_test_123', $output->url);

    self::assertInstanceOf(StartCheckoutCommand::class, $captured);
    self::assertSame(self::ORGANIZATION_ID, $captured->organizationId);
    self::assertSame('pro', $captured->planKey);
    self::assertSame('month', $captured->interval);
  }

  #[Test]
  public function testItRefusesAnUnauthenticatedCaller(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $processor = new StartCheckoutProcessor(
      $commandBus,
      $this->createStub(OrganizationAccessPort::class),
      $security,
    );

    $this->expectException(AccessDeniedHttpException::class);
    $this->expectExceptionMessage('Authentication required.');

    $processor->process($this->input(), new Post(), ['organizationId' => self::ORGANIZATION_ID]);
  }

  #[Test]
  public function testItRefusesARequestWithoutAnOrganizationUriVariable(): void
  {
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('Organization identifier is required.');

    $this->createProcessor($commandBus)->process($this->input(), new Post(), []);
  }

  #[Test]
  public function testItRefusesACallerWithoutTheSettingsWritePermission(): void
  {
    $access = $this->createStub(OrganizationAccessPort::class);
    $access->method('hasPermission')->willReturn(false);

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $processor = new StartCheckoutProcessor($commandBus, $access, $this->authenticatedSecurity());

    $this->expectException(AccessDeniedHttpException::class);
    $this->expectExceptionMessage('Missing organization.settings.write permission.');

    $processor->process($this->input(), new Post(), ['organizationId' => self::ORGANIZATION_ID]);
  }

  #[Test]
  public function testItMapsAnInvalidPlanFailureToABadRequest(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(
      MessengerRuntimeException::wrap(new InvalidArgumentException('Unknown plan key "gold".')),
    );

    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('Unknown plan key "gold".');

    $this->createProcessor($commandBus)
      ->process($this->input(), new Post(), ['organizationId' => self::ORGANIZATION_ID]);
  }

  #[Test]
  public function testItRethrowsAMessengerFailureItCannotClassify(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(
      MessengerRuntimeException::wrap(new RuntimeException('Stripe is unreachable.')),
    );

    $this->expectException(MessengerRuntimeException::class);

    $this->createProcessor($commandBus)
      ->process($this->input(), new Post(), ['organizationId' => self::ORGANIZATION_ID]);
  }

  /**
   * Method input.
   *
   * @return StartCheckoutInput a valid monthly pro-plan checkout input
   */
  private function input(): StartCheckoutInput
  {
    $input = new StartCheckoutInput();
    $input->planKey = 'pro';
    $input->interval = 'month';

    return $input;
  }

  /**
   * Method createProcessor.
   *
   * @param CommandBusPort $commandBus the command bus to dispatch through
   *
   * @return StartCheckoutProcessor a processor whose caller is authenticated and permitted
   */
  private function createProcessor(CommandBusPort $commandBus): StartCheckoutProcessor
  {
    $access = $this->createStub(OrganizationAccessPort::class);
    $access->method('hasPermission')->willReturn(true);

    return new StartCheckoutProcessor($commandBus, $access, $this->authenticatedSecurity());
  }

  /**
   * Method authenticatedSecurity.
   *
   * @return Security a security stub returning an authenticated user
   */
  private function authenticatedSecurity(): Security
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(new SecurityUser(
      id: self::USER_ID,
      email: 'owner@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
      scopes: [],
      isActive: true,
    ));

    return $security;
  }
}
