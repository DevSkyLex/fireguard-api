<?php

declare(strict_types=1);

namespace Tests\Client\Application\UseCase\Query\ValidateClientCredentials;

use Client\Application\Port\Outbound\ClientRepositoryPort;
use Client\Application\UseCase\Query\ValidateClientCredentials\{
  ValidateClientCredentialsHandler,
  ValidateClientCredentialsQuery,
  ValidateClientCredentialsResult
};
use Client\Domain\Model\Client;
use Client\Domain\ValueObject\{
  ClientId,
  ClientName,
  ClientSecret
};
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Shared\Application\Port\Outbound\HashingPort;
use Shared\Domain\ValueObject\{
  GrantType,
  GrantTypes,
  RedirectUri,
  Scope,
  Scopes
};
use Tests\Helper\TestEventIdProvider;

/**
 * Test ValidateClientCredentialsHandlerTest
 * @final
 *
 * Test class for ValidateClientCredentialsHandler.
 *
 * @category Handler Tests
 * @package Tests\Client\Application\UseCase\Query\ValidateClientCredentials
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: ValidateClientCredentialsHandler::class)]
final class ValidateClientCredentialsHandlerTest extends TestCase
{
  //#region Methods
  /**
   * Method testInvokeReturnsValidResultWhenCredentialsAreCorrect
   *
   * Test that __invoke returns valid result
   * when credentials are correct
   *
   * @access public
   *
   * @return void No return value
   */
  #[Test]
  public function testInvokeReturnsValidResultWhenCredentialsAreCorrect(): void
  {
    $clientId = '123e4567-e89b-12d3-a456-426614174000';
    $plainSecret = 'secret';

    // Create real client
    $client = Client::register(
      id: new ClientId($clientId),
      name: new ClientName('Test Client'),
      secret: new ClientSecret(password_hash($plainSecret, PASSWORD_BCRYPT)),
      redirectUris: [new RedirectUri('https://example.com')],
      grantTypes: new GrantTypes(GrantType::AUTHORIZATION_CODE),
      scopes: new Scopes(Scope::READ),
      eventIdProvider: new TestEventIdProvider(),
    );

    // Mocks
    $repository = $this->createMock(ClientRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findById')
      ->with(self::equalTo(new ClientId($clientId)))
      ->willReturn($client);

    $hashing = $this->createMock(HashingPort::class);
    $hashing->expects(self::once())
      ->method('verify')
      ->willReturn(true);

    // Query
    $query = new ValidateClientCredentialsQuery(clientId: $clientId, clientSecret: $plainSecret);

    // Handler
    $handler = new ValidateClientCredentialsHandler(
      clientRepository: $repository,
      hashing: $hashing
    );

    // Execute
    $result = $handler->__invoke($query);

    // Assert
    self::assertInstanceOf(expected: ValidateClientCredentialsResult::class, actual: $result);
    self::assertTrue(condition: $result->isValid);
    self::assertSame(expected: $clientId, actual: $result->clientId);
    self::assertNotNull(actual: $result->allowedScopes);
    self::assertNotNull(actual: $result->allowedGrantTypes);
  }

  /**
   * Method testInvokeReturnsInvalidResultWhenClientNotFound
   *
   * Test that __invoke returns invalid result
   * when client is not found
   *
   * @access public
   *
   * @return void No return value
   */
  #[Test]
  public function testInvokeReturnsInvalidResultWhenClientNotFound(): void
  {
    $clientId = '123e4567-e89b-12d3-a456-426614174000';

    $repository = $this->createMock(ClientRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findById')
      ->willReturn(null);

    $hashing = $this->createMock(HashingPort::class);

    $query = new ValidateClientCredentialsQuery(clientId: $clientId, clientSecret: 'secret');

    $handler = new ValidateClientCredentialsHandler(
      clientRepository: $repository,
      hashing: $hashing
    );

    $result = $handler->__invoke($query);

    self::assertFalse(condition: $result->isValid);
    self::assertNull(actual: $result->clientId);
  }

  /**
   * Method testInvokeReturnsInvalidResultWhenSecretIsInvalid
   *
   * Test that __invoke returns invalid result
   * when secret is invalid
   *
   * @access public
   *
   * @return void No return value
   */
  #[Test]
  public function testInvokeReturnsInvalidResultWhenSecretIsInvalid(): void
  {
    $clientId = '123e4567-e89b-12d3-a456-426614174000';

    // Create real client
    $client = Client::register(
      id: new ClientId($clientId),
      name: new ClientName('Test Client'),
      secret: new ClientSecret(password_hash('secret', PASSWORD_BCRYPT)),
      redirectUris: [new RedirectUri('https://example.com')],
      grantTypes: new GrantTypes(GrantType::AUTHORIZATION_CODE),
      scopes: new Scopes(Scope::READ),
      eventIdProvider: new TestEventIdProvider(),
    );

    $repository = $this->createMock(ClientRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findById')
      ->willReturn($client);

    $hashing = $this->createMock(HashingPort::class);
    $hashing->expects(self::once())
      ->method('verify')
      ->willReturn(false);

    $query = new ValidateClientCredentialsQuery(clientId: $clientId, clientSecret: 'wrong_secret');

    $handler = new ValidateClientCredentialsHandler(
      clientRepository: $repository,
      hashing: $hashing
    );

    $result = $handler->__invoke($query);

    self::assertFalse(condition: $result->isValid);
    self::assertNull(actual: $result->clientId);
  }
  //#endregion
}

