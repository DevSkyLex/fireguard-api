<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use Auth\Application\UseCase\Command\EmailOwnership\ConfirmEmailOwnership\ConfirmEmailOwnershipResult;
use Auth\Application\UseCase\Command\EmailOwnership\StartEmailOwnership\StartEmailOwnershipResult;
use Auth\Application\UseCase\Query\EmailOwnership\GetEmailOwnership\{GetEmailOwnershipQuery, GetEmailOwnershipResult};
use Auth\Domain\Exception\EmailOwnershipException;
use Auth\Infrastructure\Security\User\SecurityUser;
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

use function json_decode;
use function json_encode;
use function str_repeat;

use const JSON_THROW_ON_ERROR;

/**
 * Test EmailOwnershipApiTest.
 *
 * @category Test
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class EmailOwnershipApiTest extends WebTestCase
{
  // #region Tests
  /**
   * @since 1.0.0
   *
   * @param string $method request method
   * @param string $path protected endpoint
   */
  #[Test]
  #[DataProvider('protectedEndpoints')]
  public function requiresAuthentication(string $method, string $path): void
  {
    $client = static::createClient();
    $client->request($method, $path, server: ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'], content: '{}');
    self::assertResponseStatusCodeSame(401);
  }

  /**
   * @since 1.0.0
   *
   * @return array<string, array{string, string}> protected routes
   */
  public static function protectedEndpoints(): array
  {
    return [
      'read' => ['GET', '/api/auth/email-ownership'],
      'start' => ['POST', '/api/auth/email-ownership/start'],
      'confirm' => ['POST', '/api/auth/email-ownership/confirm'],
    ];
  }

  /**
   * @since 1.0.0
   */
  #[Test]
  public function readUsesAuthenticatedAccountAndSerializesProof(): void
  {
    $client = static::createClient();
    $client->loginUser(new SecurityUser('11111111-1111-4111-8111-111111111111', 'member@business.example', ''), 'api');
    $bus = $this->createMock(QueryBusPort::class);
    $bus->expects(self::once())->method('ask')->with(self::callback(static fn (GetEmailOwnershipQuery $query): bool => '11111111-1111-4111-8111-111111111111' === $query->userId))->willReturn(new GetEmailOwnershipResult(false));
    static::getContainer()->set(QueryBusPort::class, $bus);
    $client->request('GET', '/api/auth/email-ownership', server: ['HTTP_ACCEPT' => 'application/ld+json']);
    self::assertResponseStatusCodeSame(200);
    $body = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
    self::assertIsArray($body);
    self::assertSame(false, $body['verified']);
  }

  /**
   * @since 1.0.0
   */
  #[Test]
  public function startPublishesOnlyChallengeAndResendDelay(): void
  {
    $client = static::createClient();
    $client->loginUser(new SecurityUser('22222222-2222-4222-8222-222222222222', 'member@business.example', ''), 'api');
    $bus = $this->createStub(CommandBusPort::class);
    $bus->method('dispatch')->willReturn(new StartEmailOwnershipResult(str_repeat('a', 64), 60));
    static::getContainer()->set(CommandBusPort::class, $bus);
    $client->request('POST', '/api/auth/email-ownership/start', server: ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'], content: '{}');
    self::assertResponseStatusCodeSame(200);
    $body = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
    self::assertIsArray($body);
    self::assertSame(str_repeat('a', 64), $body['challengeToken']);
    self::assertSame(60, $body['canResendIn']);
  }

  /**
   * @since 1.0.0
   */
  #[Test]
  public function confirmRejectsMalformedCodeBeforeDispatch(): void
  {
    $client = static::createClient();
    $client->loginUser(new SecurityUser('33333333-3333-4333-8333-333333333333', 'member@business.example', ''), 'api');
    $bus = $this->createMock(CommandBusPort::class);
    $bus->expects(self::never())->method('dispatch');
    static::getContainer()->set(CommandBusPort::class, $bus);
    $client->request('POST', '/api/auth/email-ownership/confirm', server: ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'], content: '{"challengeToken":"invalid","code":"abc"}');
    self::assertResponseStatusCodeSame(422);
  }

  /**
   * @since 1.0.0
   */
  #[Test]
  public function invalidChallengeReturnsNeutralClientError(): void
  {
    $client = static::createClient();
    $client->loginUser(new SecurityUser('44444444-4444-4444-8444-444444444444', 'member@business.example', ''), 'api');
    $bus = $this->createStub(CommandBusPort::class);
    $bus->method('dispatch')->willThrowException(new EmailOwnershipException());
    static::getContainer()->set(CommandBusPort::class, $bus);
    $client->request('POST', '/api/auth/email-ownership/confirm', server: ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'], content: json_encode(['challengeToken' => str_repeat('a', 64), 'code' => '123456'], JSON_THROW_ON_ERROR));
    self::assertResponseStatusCodeSame(400);
  }

  /**
   * @since 1.0.0
   */
  #[Test]
  public function confirmationReturnsVerifiedAfterSuccessfulConsumption(): void
  {
    $client = static::createClient();
    $client->loginUser(new SecurityUser('55555555-5555-4555-8555-555555555555', 'member@business.example', ''), 'api');
    $bus = $this->createStub(CommandBusPort::class);
    $bus->method('dispatch')->willReturn(new ConfirmEmailOwnershipResult(true));
    static::getContainer()->set(CommandBusPort::class, $bus);
    $client->request('POST', '/api/auth/email-ownership/confirm', server: ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'], content: json_encode(['challengeToken' => str_repeat('a', 64), 'code' => '123456'], JSON_THROW_ON_ERROR));
    self::assertResponseStatusCodeSame(200);
    $body = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
    self::assertIsArray($body);
    self::assertSame(true, $body['verified']);
  }
  // #endregion
}
