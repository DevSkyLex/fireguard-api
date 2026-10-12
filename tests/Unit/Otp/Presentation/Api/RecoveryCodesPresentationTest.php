<?php

declare(strict_types=1);

namespace Tests\Unit\Otp\Presentation\Api;

use ApiPlatform\Metadata\{Get, Post};
use Auth\Application\Contract\User\AuthenticatedUser;
use DateTimeImmutable;
use Otp\Application\UseCase\Command\Totp\GenerateRecoveryCodes\{GenerateRecoveryCodesCommand, GenerateRecoveryCodesResult};
use Otp\Application\UseCase\Query\Totp\GetRecoveryCodesStatus\{GetRecoveryCodesStatusQuery, GetRecoveryCodesStatusResult};
use Otp\Presentation\Api\Dto\Input\Totp\GenerateRecoveryCodesInput;
use Otp\Presentation\Api\Processor\Totp\GenerateRecoveryCodesProcessor;
use Otp\Presentation\Api\Provider\Totp\GetRecoveryCodesStatusProvider;
use Otp\Presentation\Api\Service\RecoveryCodesActor;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{TooManyRequestsHttpException, UnauthorizedHttpException};
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Component\Security\Core\User\UserInterface;

use function array_keys;
use function get_object_vars;
use function hash;

/**
 * Class RecoveryCodesPresentationTest
 *
 * Proves immutable ownership, safe metadata mapping, authentication denial and generation throttling.
 *
 * @category Test
 */
final class RecoveryCodesPresentationTest extends TestCase
{
  public function testProviderReturnsOnlySafeMetadataForAuthenticatedUuid(): void
  {
    $bus = $this->createMock(QueryBusPort::class);
    $bus->expects(self::once())->method('ask')->with(self::callback(static fn (GetRecoveryCodesStatusQuery $query): bool => 'user-uuid' === $query->userId))->willReturn(new GetRecoveryCodesStatusResult(9, new DateTimeImmutable('2026-10-11T10:00:00+00:00')));
    $output = new GetRecoveryCodesStatusProvider($bus, $this->actor())->provide(new Get());
    self::assertSame(9, $output->remainingCount);
    self::assertSame('2026-10-11T10:00:00+00:00', $output->generatedAt);
    self::assertSame(['remainingCount', 'generatedAt'], array_keys(get_object_vars($output)));
  }

  public function testProviderDeniesAnonymousWithoutQuery(): void
  {
    $bus = $this->createMock(QueryBusPort::class);
    $bus->expects(self::never())->method('ask');
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);
    $this->expectException(UnauthorizedHttpException::class);
    new GetRecoveryCodesStatusProvider($bus, new RecoveryCodesActor($security))->provide(new Get());
  }

  public function testProcessorMapsCommittedCodesAndProof(): void
  {
    $bus = $this->createMock(CommandBusPort::class);
    $bus->expects(self::once())->method('dispatch')->with(self::callback(static fn (GenerateRecoveryCodesCommand $command): bool => 'user-uuid' === $command->userId && '123456' === $command->code))->willReturn(new GenerateRecoveryCodesResult(['proof'], new DateTimeImmutable('2026-10-11T10:00:00+00:00')));
    $input = new GenerateRecoveryCodesInput();
    $input->code = '123456';
    $output = new GenerateRecoveryCodesProcessor($bus, $this->actor())->process($input, new Post());
    self::assertSame(['proof'], $output->codes);
    self::assertSame('2026-10-11T10:00:00+00:00', $output->generatedAt);
  }

  public function testGenerationRateLimitDeniesBeforeDispatch(): void
  {
    $limiter = new RateLimiterFactory(['id' => 'recovery', 'policy' => 'fixed_window', 'limit' => 1, 'interval' => '1 minute'], new InMemoryStorage());
    $limiter->create(hash('sha256', 'user-uuid'))->consume();
    $bus = $this->createMock(CommandBusPort::class);
    $bus->expects(self::never())->method('dispatch');
    $input = new GenerateRecoveryCodesInput();
    $input->code = '123456';
    $this->expectException(TooManyRequestsHttpException::class);
    new GenerateRecoveryCodesProcessor($bus, $this->actor(), $limiter)->process($input, new Post());
  }

  private function actor(): RecoveryCodesActor
  {
    $user = new class () implements UserInterface, AuthenticatedUser {
      public function getId(): string
      {
        return 'user-uuid';
      }

      public function getUserIdentifier(): string
      {
        return 'mutable@example.test';
      }

      public function getRoles(): array
      {
        return ['ROLE_USER'];
      }

      public function eraseCredentials(): void
      {
      }
    };
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($user);

    return new RecoveryCodesActor($security);
  }
}
