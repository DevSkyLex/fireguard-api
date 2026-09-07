<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Application\Service\Federation;

use Auth\Application\Contract\Federation\{FederatedAuthorization, FederatedFlow};
use Auth\Application\Port\Outbound\Federation\{FederatedFlowRepositoryPort, FederatedIdentityRepositoryPort, FederatedProviderClientPort};
use Auth\Application\Service\Federation\{FederatedAuthenticationService, SessionIssuer};
use Auth\Domain\Exception\Federation\FederatedAuthException;
use Auth\Domain\ValueObject\Federation\FederatedProvider;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Shared\Application\Port\Outbound\TransactionManagerPort;
use User\Application\Port\Inbound\FederatedUserPort;

use function hash;

/**
 * Test FederatedAuthenticationServiceTest.
 *
 * @category Service Tests
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(FederatedAuthenticationService::class)]
final class FederatedAuthenticationServiceTest extends TestCase
{
  /**
   * Verifies that sign-in state uses PKCE data and rejects an external destination.
   *
   * @since 1.0.0
   */
  #[Test]
  public function testStartLoginPersistsSafeShortLivedFlow(): void
  {
    /** @var FederatedProviderClientPort&MockObject $providerClient */
    $providerClient = $this->createMock(FederatedProviderClientPort::class);
    $providerClient->expects(self::once())
      ->method('isEnabled')
      ->with(FederatedProvider::GOOGLE)
      ->willReturn(true);
    $state = null;
    $providerClient->expects(self::once())
      ->method('start')
      ->with(
        FederatedProvider::GOOGLE,
        'https://app.example.com/auth/federated/google/callback',
        self::callback(static function (string $value) use (&$state): bool {
          $state = $value;

          return '' !== $value;
        }),
      )
      ->willReturn(new FederatedAuthorization('https://accounts.example/authorize', 'pkce-verifier'));

    /** @var FederatedFlowRepositoryPort&MockObject $flows */
    $flows = $this->createMock(FederatedFlowRepositoryPort::class);
    $flows->expects(self::once())
      ->method('save')
      ->with(self::callback(static function (FederatedFlow $flow) use (&$state): bool {
        return null !== $state
          && hash('sha256', $state) === $flow->stateHash
          && 'pkce-verifier' === $flow->codeVerifier
          && '/' === $flow->returnUrl
          && $flow->expiresAt > new DateTimeImmutable('+9 minutes')
          && $flow->expiresAt <= new DateTimeImmutable('+10 minutes');
      }));

    $service = $this->service($providerClient, $flows);

    self::assertSame(
      'https://accounts.example/authorize',
      $service->startLogin(FederatedProvider::GOOGLE, 'https://attacker.example/redirect'),
    );
  }

  /**
   * Verifies that a disabled provider never creates a persisted authorization flow.
   *
   * @since 1.0.0
   */
  #[Test]
  public function testStartLoginRejectsDisabledProvider(): void
  {
    $providerClient = $this->createStub(FederatedProviderClientPort::class);
    $providerClient->method('isEnabled')->willReturn(false);
    $flows = $this->createMock(FederatedFlowRepositoryPort::class);
    $flows->expects(self::never())->method('save');

    $this->expectException(FederatedAuthException::class);
    $this->expectExceptionMessage('unavailable');

    $this->service($providerClient, $flows)->startLogin(FederatedProvider::MICROSOFT, '/dashboard');
  }

  /**
   * Creates the service with unused collaboration boundaries replaced by mocks.
   *
   * @since 1.0.0
   */
  private function service(
    FederatedProviderClientPort $providerClient,
    FederatedFlowRepositoryPort $flows,
  ): FederatedAuthenticationService {
    /** @var SessionIssuer $sessionIssuer */
    $sessionIssuer = new ReflectionClass(SessionIssuer::class)->newInstanceWithoutConstructor();

    return new FederatedAuthenticationService(
      providerClient: $providerClient,
      flows: $flows,
      identities: $this->createStub(FederatedIdentityRepositoryPort::class),
      transaction: $this->createStub(TransactionManagerPort::class),
      users: $this->createStub(FederatedUserPort::class),
      sessionIssuer: $sessionIssuer,
      frontendUrl: 'https://app.example.com',
    );
  }
}
