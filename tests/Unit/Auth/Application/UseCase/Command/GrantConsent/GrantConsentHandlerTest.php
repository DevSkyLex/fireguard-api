<?php

declare(strict_types=1);

namespace Tests\Auth\Application\UseCase\Command\GrantConsent;

use OAuth\Application\Port\Outbound\ConsentRepositoryPort;
use OAuth\Application\UseCase\Command\GrantConsent\GrantConsentCommand;
use OAuth\Application\UseCase\Command\GrantConsent\GrantConsentHandler;
use OAuth\Application\UseCase\Command\GrantConsent\GrantConsentResult;
use OAuth\Domain\Model\Consent;
use OAuth\Domain\ValueObject\ConsentId;
use OAuth\Domain\ValueObject\Scope;
use OAuth\Domain\ValueObject\Scopes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shared\Application\Factory\UuidFactory;

/**
 * Test GrantConsentHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: GrantConsentHandler::class)]
final class GrantConsentHandlerTest extends TestCase
{
  // #region Methods
  /**
   * Method testInvokeCreatesNewConsentWhenNoneExists.
   *
   * Test that __invoke creates new consent when none exists.
   */
  #[Test]
  public function testInvokeCreatesNewConsentWhenNoneExists(): void
  {
    $consentId = '123e4567-e89b-12d3-a456-426614174000';

    $uuidFactory = $this->createMock(UuidFactory::class);
    $uuidFactory->expects(self::once())
      ->method('create')
      ->with(ConsentId::class)
      ->willReturn(new ConsentId($consentId));

    $repository = $this->createMock(ConsentRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findByUserAndClient')
      ->willReturn(null);
    $repository->expects(self::once())
      ->method('save')
      ->with(self::isInstanceOf(Consent::class));

    $command = new GrantConsentCommand(
      userId: 'user-123',
      clientId: 'client-456',
      scopes: ['OPENID', 'PROFILE'],
    );

    $handler = new GrantConsentHandler(
      consentRepository: $repository,
      uuidFactory: $uuidFactory,
    );

    $result = $handler->__invoke(command: $command);

    self::assertInstanceOf(GrantConsentResult::class, $result);
    self::assertEquals($consentId, $result->consentId);
    self::assertTrue($result->isNew);
  }

  /**
   * Method testInvokeUpdatesExistingConsent.
   *
   * Test that __invoke updates existing consent.
   */
  #[Test]
  public function testInvokeUpdatesExistingConsent(): void
  {
    $consentId = '123e4567-e89b-12d3-a456-426614174000';

    $existingConsent = Consent::grant(
      id: new ConsentId($consentId),
      userId: 'user-123',
      clientId: 'client-456',
      scopes: new Scopes(Scope::OPENID),
    );

    $uuidFactory = $this->createMock(UuidFactory::class);
    $uuidFactory->expects(self::never())->method('create');

    $repository = $this->createMock(ConsentRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findByUserAndClient')
      ->willReturn($existingConsent);
    $repository->expects(self::once())
      ->method('save');

    $command = new GrantConsentCommand(
      userId: 'user-123',
      clientId: 'client-456',
      scopes: ['OPENID', 'PROFILE', 'EMAIL'],
    );

    $handler = new GrantConsentHandler(
      consentRepository: $repository,
      uuidFactory: $uuidFactory,
    );

    $result = $handler->__invoke(command: $command);

    self::assertFalse($result->isNew);
    self::assertEquals($consentId, $result->consentId);
  }
  // #endregion
}
