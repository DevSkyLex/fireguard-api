<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Application\Service\Federation;

use Auth\Application\Contract\Token\SignInGrantType;
use Auth\Application\Port\Outbound\Internal\Mfa\ChallengeGeneratorPort;
use Auth\Application\Port\Outbound\{JwtTokenServicePort, SessionTrackingPort, TrustedDeviceCheckPort};
use Auth\Application\Port\Outbound\Mfa\TotpEnrollmentCheckPort;
use Auth\Application\Service\Federation\SessionIssuer;
use Auth\Application\UseCase\Command\Mfa\MfaChallenge\MfaChallengeResult;
use Auth\Infrastructure\Adapter\Policy\ConfiguredAuthenticationPolicyAdapter;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\EventDispatcherPort;
use User\Application\Port\Inbound\FederatedUserPort;

/**
 * Class SessionIssuerPolicyTest
 *
 * Covers project MFA policy denial without weakening factor selection or granting tokens.
 *
 * @category Service Tests
 */
#[CoversClass(SessionIssuer::class)]
final class SessionIssuerPolicyTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testDisabledTrustedDeviceBypassStillRequiresMfa(): void
  {
    $tokens = $this->createMock(JwtTokenServicePort::class);
    $tokens->expects(self::never())->method('generateTokens');
    $tokens->expects(self::once())->method('generatePreAuthToken')->willReturn('pre-authentication-token');
    $challenge = $this->createMock(ChallengeGeneratorPort::class);
    $challenge->expects(self::once())->method('generate')->willReturn(new MfaChallengeResult('challenge', 'masked', new DateTimeImmutable('+5 minutes'), 5));
    $trusted = $this->createMock(TrustedDeviceCheckPort::class);
    $trusted->expects(self::never())->method('isTrusted');
    $enrollment = $this->createStub(TotpEnrollmentCheckPort::class);
    $enrollment->method('isEnrolled')->willReturn(true);
    $tracking = $this->createMock(SessionTrackingPort::class);
    $tracking->expects(self::never())->method('recordSession');
    $events = $this->createMock(EventDispatcherPort::class);
    $events->expects(self::never())->method('dispatch');
    $issuer = new SessionIssuer($tokens, $challenge, $tracking, $events, $trusted, $enrollment, $this->createStub(FederatedUserPort::class), new ConfiguredAuthenticationPolicyAdapter(trustedDeviceMfaBypassEnabled: false));

    $result = $issuer->issue('user', 'user@example.com', null, null, 'valid-trusted-token', false, SignInGrantType::PASSWORD);
    self::assertTrue($result->mfaRequired);
    self::assertSame('totp', $result->mfaMethod);
    self::assertNull($result->accessToken);
  }
  // #endregion
}
