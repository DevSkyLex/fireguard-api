<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use Auth\Application\Port\Outbound\AuthenticationPolicyPort;
use Auth\Application\UseCase\Command\Session\Login\{LoginCommand, LoginResult};
use Auth\Infrastructure\Adapter\Policy\ConfiguredAuthenticationPolicyAdapter;
use PHPUnit\Framework\Attributes\Test;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use User\Application\Port\Outbound\PasswordPolicyPort;
use User\Infrastructure\Adapter\Policy\ConfiguredPasswordPolicyAdapter;

use function array_intersect_key;
use function json_decode;
use function json_encode;

/**
 * Class PasswordPolicyApiTest
 *
 * Verifies the public requirement contract and configurable policy denial at the HTTP boundary.
 *
 * @category Functional Tests
 */
final class PasswordPolicyApiTest extends WebTestCase
{
  // #region Methods
  #[Test]
  public function testPublicRequirementsExposeExactlyTheDefaultPolicyFields(): void
  {
    $client = static::createClient();
    $client->request('GET', '/api/auth/password-policy', server: ['HTTP_ACCEPT' => 'application/ld+json']);
    self::assertResponseIsSuccessful();
    $body = json_decode($client->getResponse()->getContent() ?: '{}', true);
    self::assertIsArray($body);
    $expected = [
      'minimumLength' => 8,
      'maximumLength' => 128,
      'requireUppercase' => true,
      'requireLowercase' => true,
      'requireDigit' => true,
      'specialCharacters' => '@$!%*?&#',
      'allowedInitialCharacters' => 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789@$!%*?&#',
    ];
    self::assertSame($expected, array_intersect_key($body, $expected));
  }

  #[Test]
  public function testAlternateProjectPolicyIsPublished(): void
  {
    $client = static::createClient();
    static::getContainer()->set(PasswordPolicyPort::class, new ConfiguredPasswordPolicyAdapter(4, 12, false, false, true, ''));
    $client->request('GET', '/api/auth/password-policy', server: ['HTTP_ACCEPT' => 'application/ld+json']);
    self::assertResponseIsSuccessful();
    $body = json_decode($client->getResponse()->getContent() ?: '{}', true);
    self::assertIsArray($body);
    $expected = [
      'minimumLength' => 4,
      'maximumLength' => 12,
      'requireUppercase' => false,
      'requireLowercase' => false,
      'requireDigit' => true,
      'specialCharacters' => '',
      'allowedInitialCharacters' => null,
    ];
    self::assertSame($expected, array_intersect_key($body, $expected));
  }

  #[Test]
  public function testPublicPolicyCannotBeWritten(): void
  {
    $client = static::createClient();
    $client->jsonRequest('POST', '/api/auth/password-policy', ['minimumLength' => 1]);
    self::assertResponseStatusCodeSame(405);
  }

  #[Test]
  public function testDisabledSignupReturnsForbidden(): void
  {
    $client = static::createClient();
    static::getContainer()->set(AuthenticationPolicyPort::class, new ConfiguredAuthenticationPolicyAdapter(signupEnabled: false));
    $client->request('POST', '/api/auth/register', server: ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'], content: json_encode([
      'email' => 'reviewer@example.com',
      'password' => 'Abcdef1!',
      'firstName' => 'Review',
      'lastName' => 'User',
    ]) ?: '');
    self::assertResponseStatusCodeSame(403);
  }

  #[Test]
  public function testStricterCreationRulesDoNotRejectLegacyCredentialDuringLogin(): void
  {
    $client = static::createClient();
    static::getContainer()->set(PasswordPolicyPort::class, new ConfiguredPasswordPolicyAdapter(12, 128));
    $commands = $this->createMock(CommandBusPort::class);
    $commands->expects(self::once())->method('dispatch')->with(self::callback(static fn (LoginCommand $command): bool => 'Abcdef1!' === $command->password))->willReturn(LoginResult::failed());
    static::getContainer()->set(CommandBusPort::class, $commands);
    $client->request('POST', '/api/auth/login', server: ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'], content: json_encode(['email' => 'user@example.com', 'password' => 'Abcdef1!']) ?: '');
    self::assertResponseStatusCodeSame(401);
  }
  // #endregion
}
