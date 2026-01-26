<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Infrastructure\OAuth2\League\Adapter;

use OAuth\Application\UseCase\Query\Client\ValidateClientCredentials\ValidateClientCredentialsResult;
use OAuth\Infrastructure\OAuth2\League\Adapter\ClientValidationAdapter;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Port\Inbound\QueryBusPort;

/**
 * Test ClientValidationAdapterTest.
 *
 * @category Adapter Tests
 */
#[CoversClass(className: ClientValidationAdapter::class)]
final class ClientValidationAdapterTest extends TestCase
{
  // #region Tests
  #[Test]
  public function testValidateCredentialsReturnsTrueWhenValid(): void
  {
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->willReturn(new ValidateClientCredentialsResult(isValid: true, clientId: 'client-123'));

    $adapter = new ClientValidationAdapter($queryBus);

    self::assertTrue($adapter->validateCredentials('client-123', 'secret'));
  }

  #[Test]
  public function testValidateCredentialsReturnsFalseOnException(): void
  {
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->willThrowException(new RuntimeException('boom'));

    $adapter = new ClientValidationAdapter($queryBus);

    self::assertFalse($adapter->validateCredentials('client-123', 'secret'));
  }
  // #endregion
}
