<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use App\Tests\E2E\OAuth2WebTestCase;
use Auth\Application\Port\Outbound\JwtTokenServicePort;
use DAMA\DoctrineTestBundle\PHPUnit\SkipDatabaseRollback;
use Otp\Application\Port\Outbound\Internal\Challenge\OtpRepositoryPort;
use Otp\Domain\Model\Otp;
use Otp\Domain\ValueObject\{OtpChannel, OtpId, OtpPurpose};
use Otp\Infrastructure\Adapter\Notifier\TotpAdapter;
use ReflectionMethod;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Tests\Support\Database\RootCommitDatabaseIsolationTrait;
use User\Application\Port\Inbound\FederatedUserPort;

use function json_encode;
use function time;

/**
 * Class MfaRecoveryCodesApiTest
 *
 * Exercises generation, metadata, recovery login and independent proof for authenticator removal.
 *
 * @category Functional Test
 */
#[SkipDatabaseRollback]
final class MfaRecoveryCodesApiTest extends OAuth2WebTestCase
{
  use RootCommitDatabaseIsolationTrait;

  public function testLotGenerationRecoveryLoginAndProtectedDisable(): void
  {
    $client = static::createClientWithFixtures();
    $actor = $this->authenticateFreshUser($client);
    $this->request($client, 'GET', '/api/otp/totp/recovery-codes', $actor['token']);
    self::assertSame(200, $client->getResponse()->getStatusCode());
    $status = $this->body($client);
    self::assertSame(0, $status['remainingCount'] ?? null);
    self::assertArrayHasKey('generatedAt', $status);
    self::assertNull($status['generatedAt']);
    $this->request($client, 'POST', '/api/otp/totp/recovery-codes', $actor['token'], ['code' => '123456']);
    self::assertSame(404, $client->getResponse()->getStatusCode());

    $this->request($client, 'POST', '/api/otp/totp/setup', $actor['token']);
    self::assertSame(201, $client->getResponse()->getStatusCode());
    $setup = $this->body($client);
    self::assertIsString($setup['secret'] ?? null);
    $proof = $this->code($setup['secret']);
    $this->request($client, 'POST', '/api/otp/totp/confirm', $actor['token'], ['code' => $proof]);
    self::assertSame(200, $client->getResponse()->getStatusCode());
    $this->request($client, 'POST', '/api/otp/totp/recovery-codes', $actor['token'], ['code' => $proof]);
    self::assertSame(200, $client->getResponse()->getStatusCode());
    $lot = $this->body($client);
    self::assertIsArray($lot['codes'] ?? null);
    self::assertCount(10, $lot['codes']);
    $first = $lot['codes'][0];
    $second = $lot['codes'][1];
    self::assertIsString($first);
    self::assertIsString($second);
    self::assertIsString($lot['generatedAt'] ?? null);
    $this->request($client, 'GET', '/api/otp/totp/recovery-codes', $actor['token']);
    self::assertSame(200, $client->getResponse()->getStatusCode());
    $status = $this->body($client);
    self::assertSame(10, $status['remainingCount'] ?? null);
    self::assertSame($lot['generatedAt'], $status['generatedAt'] ?? null);
    self::assertArrayNotHasKey('codes', $status);
    self::assertArrayNotHasKey('recoveryCodeHashes', $status);

    $jwt = static::getContainer()->get(JwtTokenServicePort::class);
    self::assertInstanceOf(JwtTokenServicePort::class, $jwt);
    $repo = static::getContainer()->get(OtpRepositoryPort::class);
    self::assertInstanceOf(OtpRepositoryPort::class, $repo);
    $users = static::getContainer()->get(FederatedUserPort::class);
    self::assertInstanceOf(FederatedUserPort::class, $users);
    $profile = $users->find($actor['userId']);
    self::assertNotNull($profile);
    $challenge = Otp::generate(new OtpId('33333333-3333-4333-8333-333333333333'), $actor['userId'], OtpPurpose::LOGIN, OtpChannel::TOTP, $profile->email);
    $repo->save($challenge);
    $preAuth = $jwt->generatePreAuthToken($actor['userId'], $challenge->challengeToken()->value, $profile->email, ['READ', 'WRITE']);
    $this->request($client, 'POST', '/api/auth/mfa/verify', null, ['preAuthToken' => $preAuth, 'code' => $first, 'method' => 'recovery_code']);
    self::assertSame(200, $client->getResponse()->getStatusCode());
    $completed = $this->body($client);
    self::assertIsString($completed['access_token'] ?? null);
    $session = $completed['access_token'];
    $this->request($client, 'GET', '/api/otp/totp/recovery-codes', $session);
    self::assertSame(9, $this->body($client)['remainingCount'] ?? null);
    $this->request($client, 'POST', '/api/otp/totp/disable', $session, ['code' => $first, 'proofType' => 'recovery_code']);
    self::assertSame(422, $client->getResponse()->getStatusCode());
    $this->request($client, 'POST', '/api/otp/totp/disable', $session, ['code' => $second, 'proofType' => 'recovery_code']);
    self::assertSame(200, $client->getResponse()->getStatusCode());
    $this->request($client, 'GET', '/api/otp/totp/recovery-codes', $session);
    $status = $this->body($client);
    self::assertSame(0, $status['remainingCount'] ?? null);
    self::assertArrayHasKey('generatedAt', $status);
    self::assertNull($status['generatedAt']);
  }

  public function testRecoveryEndpointsRequireAuthenticationAndWrongTotpCannotExposeCodes(): void
  {
    $client = static::createClientWithFixtures();
    foreach (['GET', 'POST'] as $method) {
      $this->request($client, $method, '/api/otp/totp/recovery-codes', null, 'POST' === $method ? ['code' => '123456'] : []);
      self::assertSame(401, $client->getResponse()->getStatusCode());
    }
    $actor = $this->authenticateFreshUser($client);
    $this->request($client, 'POST', '/api/otp/totp/setup', $actor['token']);
    $setup = $this->body($client);
    self::assertIsString($setup['secret'] ?? null);
    $valid = $this->code($setup['secret']);
    $this->request($client, 'POST', '/api/otp/totp/confirm', $actor['token'], ['code' => $valid]);
    self::assertSame(200, $client->getResponse()->getStatusCode());
    $wrong = '000000' === $valid ? '111111' : '000000';
    $this->request($client, 'POST', '/api/otp/totp/recovery-codes', $actor['token'], ['code' => $wrong]);
    self::assertSame(422, $client->getResponse()->getStatusCode());
    self::assertArrayNotHasKey('codes', $this->body($client));
  }

  public function testSetupReceiptReplaysExactSecretAndFreshGrantRevocationDeniesDisclosure(): void
  {
    $client = static::createClientWithFixtures();
    $actor = $this->authenticateFreshUser($client);
    $server = ['HTTP_ACCEPT' => 'application/ld+json', 'CONTENT_TYPE' => 'application/ld+json', 'HTTP_AUTHORIZATION' => 'Bearer ' . $actor['token'], 'HTTP_IDEMPOTENCY_KEY' => 'totp-setup-receipt'];
    $client->request('POST', '/api/otp/totp/setup', server: $server);
    self::assertSame(201, $client->getResponse()->getStatusCode());
    $first = $this->body($client);
    self::assertIsString($first['secret'] ?? null);
    $client->request('POST', '/api/otp/totp/setup', server: $server);
    self::assertSame(201, $client->getResponse()->getStatusCode());
    $second = $this->body($client);
    self::assertSame($first['secret'], $second['secret'] ?? null);
    self::assertSame($first['qrCodeUri'] ?? null, $second['qrCodeUri'] ?? null);
    $manager = static::getContainer()->get('doctrine.orm.auth_entity_manager');
    self::assertInstanceOf(\Doctrine\ORM\EntityManagerInterface::class, $manager);
    $manager->getConnection()->executeStatement('DELETE FROM role_assignments WHERE subject_type = ? AND subject_id = ?', ['user', $actor['userId']]);
    $client->request('POST', '/api/otp/totp/setup', server: $server);
    self::assertContains($client->getResponse()->getStatusCode(), [403, 409]);
    self::assertArrayNotHasKey('secret', $this->body($client));
    self::assertArrayNotHasKey('qrCodeUri', $this->body($client));
  }

  /**
   * @param array<string, string> $body request body
   */
  private function request(KernelBrowser $client, string $method, string $url, ?string $session, array $body = []): void
  {
    $server = ['HTTP_ACCEPT' => 'application/ld+json', 'CONTENT_TYPE' => 'application/ld+json'];
    if (null !== $session) {
      $server['HTTP_AUTHORIZATION'] = 'Bearer ' . $session;
    }
    $client->request($method, $url, server: $server, content: [] === $body ? null : (string) json_encode($body));
  }

  /**
   * @return array<string, mixed> decoded response
   */
  private function body(KernelBrowser $client): array
  {
    return $this->decodeJsonResponse($client->getResponse()->getContent() ?: '{}');
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
}
