<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Presentation\Api\EmailOwnership;

use ApiPlatform\Metadata\{Get, Post};
use Auth\Application\UseCase\Command\EmailOwnership\ConfirmEmailOwnership\{ConfirmEmailOwnershipCommand, ConfirmEmailOwnershipResult};
use Auth\Application\UseCase\Command\EmailOwnership\StartEmailOwnership\StartEmailOwnershipResult;
use Auth\Application\UseCase\Query\EmailOwnership\GetEmailOwnership\{GetEmailOwnershipQuery, GetEmailOwnershipResult};
use Auth\Infrastructure\Security\User\SecurityUser;
use Auth\Presentation\Api\Dto\Input\EmailOwnership\ConfirmEmailOwnershipInput;
use Auth\Presentation\Api\Processor\EmailOwnership\{ConfirmEmailOwnershipProcessor, StartEmailOwnershipProcessor};
use Auth\Presentation\Api\Provider\EmailOwnership\GetEmailOwnershipProvider;
use Auth\Presentation\Api\Service\EmailOwnershipActor;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, TooManyRequestsHttpException};
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

use function get_object_vars;

/**
 * Test EmailOwnershipPresentationTest.
 *
 * @category Test
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class EmailOwnershipPresentationTest extends TestCase
{
  // #region Tests
  /**
   * @since 1.0.0
   */
  #[Test]
  public function unauthenticatedReadNeverDispatches(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);
    $bus = $this->createMock(QueryBusPort::class);
    $bus->expects(self::never())->method('ask');
    $this->expectException(AccessDeniedHttpException::class);
    new GetEmailOwnershipProvider($bus, new EmailOwnershipActor($security))->provide(new Get());
  }

  /**
   * @since 1.0.0
   */
  #[Test]
  public function readExposesOnlyVerifiedState(): void
  {
    $bus = $this->createMock(QueryBusPort::class);
    $bus->expects(self::once())->method('ask')->with(self::callback(static fn (GetEmailOwnershipQuery $query): bool => 'user' === $query->userId))->willReturn(new GetEmailOwnershipResult(false));
    $output = new GetEmailOwnershipProvider($bus, $this->actor())->provide(new Get());
    self::assertSame(['verified' => false], get_object_vars($output));
  }

  /**
   * @since 1.0.0
   */
  #[Test]
  public function startIsRateLimitedBeforeAnotherEmailIsSent(): void
  {
    $bus = $this->createMock(CommandBusPort::class);
    $bus->expects(self::once())->method('dispatch')->willReturn(new StartEmailOwnershipResult('token', 60));
    $processor = new StartEmailOwnershipProcessor($bus, $this->actor(), $this->limiter());
    $output = $processor->process(null, new Post());
    self::assertSame(60, $output->canResendIn);
    $this->expectException(TooManyRequestsHttpException::class);
    $processor->process(null, new Post());
  }

  /**
   * @since 1.0.0
   */
  #[Test]
  public function confirmUsesAuthenticatedPrincipalRatherThanInputIdentifier(): void
  {
    $bus = $this->createMock(CommandBusPort::class);
    $bus->expects(self::once())->method('dispatch')->with(self::callback(static fn (ConfirmEmailOwnershipCommand $command): bool => 'user' === $command->userId && 'token' === $command->challengeToken && '123456' === $command->code))->willReturn(new ConfirmEmailOwnershipResult(true));
    $input = new ConfirmEmailOwnershipInput();
    $input->challengeToken = 'token';
    $input->code = '123456';
    self::assertTrue(new ConfirmEmailOwnershipProcessor($bus, $this->actor(), $this->limiter())->process($input, new Post())->verified);
  }
  // #endregion

  // #region Helpers
  /**
   * @since 1.0.0
   *
   * @return EmailOwnershipActor the principal resolver
   */
  private function actor(): EmailOwnershipActor
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(new SecurityUser('user', 'member@business.example', ''));

    return new EmailOwnershipActor($security);
  }

  /**
   * @since 1.0.0
   *
   * @return RateLimiterFactory isolated request budget
   */
  private function limiter(): RateLimiterFactory
  {
    return new RateLimiterFactory(['id' => 'ownership', 'policy' => 'fixed_window', 'limit' => 1, 'interval' => '1 minute'], new InMemoryStorage());
  }
  // #endregion
}
