<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use Otp\Application\Contract\Challenge\{OtpChannel, OtpPurpose};
use Otp\Application\Port\Inbound\Challenge\OtpChallengePort;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

use function json_decode;
use function json_encode;

use const JSON_THROW_ON_ERROR;

/** Verifies the actual serialized recovery contract, not just an HTTP exception header. */
final class ResendRecoveryContractTest extends WebTestCase
{
  public function testRegistrationResendReturnsAStructuredCooldown(): void
  {
    $client = self::createClient();
    /** @var OtpChallengePort $challenges */
    $challenges = self::getContainer()->get(OtpChallengePort::class);
    $challenge = $challenges->generate(
      userId: '770e8400-e29b-41d4-a716-446655440009',
      purpose: OtpPurpose::EMAIL_VERIFICATION,
      channel: OtpChannel::EMAIL,
      recipient: 'cooldown@example.test',
    );
    $client->request('POST', '/api/auth/register/resend', server: [
      'CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json',
    ], content: json_encode(['token' => $challenge->challengeToken], JSON_THROW_ON_ERROR));
    self::assertResponseStatusCodeSame(429);
    self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
    $body = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
    self::assertIsArray($body);
    self::assertSame('rate_limit_exceeded', $body['code']);
    self::assertIsInt($body['retryAfterSeconds']);
    self::assertGreaterThan(0, $body['retryAfterSeconds']);
    self::assertLessThanOrEqual(60, $body['retryAfterSeconds']);
    self::assertSame((string) $body['retryAfterSeconds'], $client->getResponse()->headers->get('Retry-After'));
  }
}
