<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use Otp\Application\Port\Outbound\Challenge\{OtpNotifierPort, OtpRepositoryPort};
use Otp\Domain\Model\Otp;
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use Shared\Application\Contract\Notification\EmailRequestDetails;
use Shared\Domain\ValueObject\Email;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Tests\Support\Factory\UserTestFactory;
use User\Application\Port\Outbound\UserRepositoryPort;
use User\Domain\Model\User\User;

use function array_keys;
use function json_decode;
use function json_encode;
use function sort;

use const JSON_THROW_ON_ERROR;

/**
 * Class PasswordResetRequestOriginApiTest
 *
 * Exercises the real reset, OTP and HTTP origin chain with delivery and persistence doubles.
 *
 * @category Functional Tests
 */
final class PasswordResetRequestOriginApiTest extends WebTestCase
{
  // #region Methods
  /**
   * @return iterable<string, array{?string}>
   */
  public static function unknownHeaders(): iterable
  {
    yield 'zero' => ['0'];
    yield 'padded zero' => [' 0 '];
    yield 'absent' => [null];
  }

  #[Test]
  #[DataProvider('unknownHeaders')]
  public function testUnknownHeaderDoesNotDistinguishAccountStates(?string $header): void
  {
    $client = static::createClient();
    $client->disableReboot();
    $active = UserTestFactory::createActive(email: 'origin-active@example.com');
    $inactive = UserTestFactory::createPending(email: 'origin-inactive@example.com');
    $users = $this->createStub(UserRepositoryPort::class);
    $users->method('findByEmail')->willReturnCallback(static fn (Email $email): ?User => match ($email->value) {
      'origin-active@example.com' => $active,
      'origin-inactive@example.com' => $inactive,
      default => null,
    });
    static::getContainer()->set(UserRepositoryPort::class, $users);
    $repository = $this->createMock(OtpRepositoryPort::class);
    $repository->expects(self::once())->method('revokeAllForUser');
    $repository->expects(self::once())->method('save');
    static::getContainer()->set(OtpRepositoryPort::class, $repository);
    $notifier = $this->createMock(OtpNotifierPort::class);
    $notifier->expects(self::once())->method('send')->willReturnCallback(static function (Otp $otp, ?EmailRequestDetails $details): void {
      self::assertNotNull($details);
      self::assertNull($details->browser);
      self::assertNull($details->operatingSystem);
    });
    static::getContainer()->set(OtpNotifierPort::class, $notifier);
    $expectedKeys = null;
    foreach (['origin-active@example.com', 'origin-absent@example.com', 'origin-inactive@example.com'] as $email) {
      $server = ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'];
      if (null !== $header) {
        $server['HTTP_USER_AGENT'] = $header;
      }
      $client->request('POST', '/api/auth/password/reset/request', server: $server, content: json_encode(['email' => $email], JSON_THROW_ON_ERROR));
      self::assertResponseStatusCodeSame(200);
      $body = json_decode($client->getResponse()->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
      self::assertIsArray($body);
      self::assertTrue($body['success']);
      self::assertIsString($body['challengeToken']);
      self::assertNotSame('', $body['challengeToken']);
      self::assertIsString($body['maskedRecipient']);
      $keys = array_keys($body);
      sort($keys);
      $expectedKeys ??= $keys;
      self::assertSame($expectedKeys, $keys);
    }
  }
  // #endregion
}
