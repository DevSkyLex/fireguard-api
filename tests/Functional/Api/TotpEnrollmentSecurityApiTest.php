<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use App\Tests\E2E\OAuth2WebTestCase;
use DateTimeImmutable;
use Otp\Application\Port\Outbound\Totp\{TotpEnrollmentRepositoryPort, TotpServicePort};
use Otp\Domain\Model\Totp\{TotpEnrollment, TotpEnrollmentAttempts, TotpEnrollmentSecrets};
use Otp\Domain\ValueObject\TotpSecret;
use Otp\Infrastructure\Adapter\Notifier\TotpAdapter;
use ReflectionMethod;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

use function json_encode;
use function time;

/**
 * Test TotpEnrollmentSecurityApiTest.
 *
 * Exercises genuine authenticated enrollment requests and current-factor protected disable.
 *
 * @category Functional Test
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class TotpEnrollmentSecurityApiTest extends OAuth2WebTestCase
{
  // #region Methods
  public function testSessionPossessionCannotReplaceAnActiveFactorAndProtectedReenrollmentWorks(): void
  {
    $client = static::createClientWithFixtures();
    $actor = $this->authenticateFreshUser($client);
    $this->request($client, 'setup', $actor['token']);
    self::assertSame(201, $client->getResponse()->getStatusCode());
    $setup = $this->decodeJsonResponse($client->getResponse()->getContent() ?: '{}');
    self::assertIsString($setup['secret'] ?? null);
    $activeSecret = $setup['secret'];
    $this->request($client, 'confirm', $actor['token'], ['code' => $this->code($activeSecret)]);
    self::assertSame(200, $client->getResponse()->getStatusCode());

    $this->request($client, 'setup', $actor['token']);
    self::assertSame(409, $client->getResponse()->getStatusCode());
    self::assertArrayNotHasKey('secret', $this->decodeJsonResponse($client->getResponse()->getContent() ?: '{}'));
    $this->request($client, 'confirm', $actor['token'], ['code' => $this->code($activeSecret)]);
    self::assertSame(409, $client->getResponse()->getStatusCode());

    // Previously persisted pending secrets must not form an alternate replacement route.
    $now = new DateTimeImmutable();
    $legacyPending = new TotpSecret('AAAAAAAAAAAAAAAA');
    $repository = $this->repository();
    $repository->save(TotpEnrollment::reconstitute(
      $actor['userId'],
      new TotpEnrollmentSecrets(new TotpSecret($activeSecret), $now, $legacyPending, $now),
      new TotpEnrollmentAttempts(0, 5),
      $now,
      $now,
    ));
    $this->request($client, 'confirm', $actor['token'], ['code' => $this->code($legacyPending->secret)]);
    self::assertSame(409, $client->getResponse()->getStatusCode());
    $stored = $this->repository()->findByUserId($actor['userId']);
    self::assertNotNull($stored);
    self::assertSame($activeSecret, $stored->activeSecret()?->secret);
    self::assertSame(0, $stored->attempts());

    $this->request($client, 'disable', $actor['token'], ['code' => $this->code($activeSecret)]);
    self::assertSame(200, $client->getResponse()->getStatusCode());
    $this->request($client, 'setup', $actor['token']);
    self::assertSame(201, $client->getResponse()->getStatusCode());
    $newSetup = $this->decodeJsonResponse($client->getResponse()->getContent() ?: '{}');
    self::assertIsString($newSetup['secret'] ?? null);
    $this->request($client, 'confirm', $actor['token'], ['code' => $this->code($newSetup['secret'])]);
    self::assertSame(200, $client->getResponse()->getStatusCode());
    $active = $this->repository()->findByUserId($actor['userId']);
    self::assertNotNull($active);
    self::assertTrue($active->isActive());
    /** @var TotpServicePort $totp */
    $totp = static::getContainer()->get(TotpServicePort::class);
    $storedSecret = $active->activeSecret();
    self::assertInstanceOf(TotpSecret::class, $storedSecret);
    self::assertTrue($totp->verify($this->code($newSetup['secret']), $storedSecret));
  }

  public function testAccountDeletionRemovesItsAuthenticatorEnrollmentAndDeniesOutsiders(): void
  {
    $client = static::createClientWithFixtures();
    $actor = $this->authenticateFreshUser($client);
    $this->request($client, 'setup', $actor['token']);
    self::assertSame(201, $client->getResponse()->getStatusCode());
    self::assertNotNull($this->repository()->findByUserId($actor['userId']));
    $outsider = $this->authenticateFreshUser($client);
    $unknownId = '99999999-9999-4999-8999-999999999999';
    foreach ([$actor['userId'], $unknownId] as $requestedId) {
      $client->request('DELETE', '/api/users/' . $requestedId, server: [
        'HTTP_ACCEPT' => 'application/ld+json', 'HTTP_AUTHORIZATION' => 'Bearer ' . $outsider['token'],
      ]);
      self::assertSame(403, $client->getResponse()->getStatusCode());
    }
    self::assertNotNull($this->repository()->findByUserId($actor['userId']));
    $adminSession = $this->authenticateAsSeededAdmin($client);
    $client->request('DELETE', '/api/users/' . $unknownId, server: [
      'HTTP_ACCEPT' => 'application/ld+json', 'HTTP_AUTHORIZATION' => 'Bearer ' . $adminSession,
    ]);
    self::assertSame(404, $client->getResponse()->getStatusCode());
    $client->request('DELETE', '/api/users/' . $actor['userId'], server: [
      'HTTP_ACCEPT' => 'application/ld+json', 'HTTP_AUTHORIZATION' => 'Bearer ' . $adminSession,
    ]);
    self::assertSame(204, $client->getResponse()->getStatusCode());
    self::assertNull($this->repository()->findByUserId($actor['userId']));
    $this->request($client, 'setup', $actor['token']);
    self::assertSame(401, $client->getResponse()->getStatusCode());
    self::assertArrayNotHasKey('secret', $this->decodeJsonResponse($client->getResponse()->getContent() ?: '{}'));
    self::assertNull($this->repository()->findByUserId($actor['userId']));
  }

  private function repository(): TotpEnrollmentRepositoryPort
  {
    $repository = static::getContainer()->get(TotpEnrollmentRepositoryPort::class);
    self::assertInstanceOf(TotpEnrollmentRepositoryPort::class, $repository);

    return $repository;
  }

  /**
   * @param array<string, string> $body
   */
  private function request(KernelBrowser $client, string $action, string $session, array $body = []): void
  {
    $client->request(
      'POST',
      '/api/otp/totp/' . $action,
      server: ['HTTP_ACCEPT' => 'application/ld+json', 'CONTENT_TYPE' => 'application/ld+json', 'HTTP_AUTHORIZATION' => 'Bearer ' . $session],
      content: [] === $body ? null : (string) json_encode($body),
    );
  }

  private function code(string $secret): string
  {
    $totp = new TotpAdapter();
    $bytes = new ReflectionMethod($totp, 'base32Decode')->invoke($totp, $secret);
    self::assertIsString($bytes);
    $code = new ReflectionMethod($totp, 'generateCode')->invoke($totp, $bytes, time());
    self::assertIsString($code);

    return $code;
  }
  // #endregion
}
