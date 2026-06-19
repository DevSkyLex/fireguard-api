<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Command\Organization\UpdateOrganizationSettings;

use Doctrine\DBAL\Driver\Exception as DoctrineDriverException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Organization\Application\Port\Outbound\OrganizationRepositoryPort;
use Organization\Application\UseCase\Command\Organization\UpdateOrganizationSettings\{
  UpdateOrganizationSettingsCommand,
  UpdateOrganizationSettingsHandler,
  UpdateOrganizationSettingsResult
};
use Organization\Domain\Exception\{OrganizationNotFoundException, OrganizationSlugAlreadyExistsException};
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationName, OrganizationSlug};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Port\Outbound\TransactionManagerPort;
use Shared\Infrastructure\Exception\TransactionExecutionException;

#[CoversClass(UpdateOrganizationSettingsHandler::class)]
final class UpdateOrganizationSettingsHandlerTest extends TestCase
{
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440010';

  private const string OWNER_USER_ID = '550e8400-e29b-41d4-a716-446655440001';

  #[Test]
  public function testInvokeAppliesProvidedFieldsAndSaves(): void
  {
    $organization = Organization::create(
      id: OrganizationId::fromString(self::ORGANIZATION_ID),
      name: new OrganizationName('Fireguard Lyon'),
      ownerUserId: self::OWNER_USER_ID,
      slug: new OrganizationSlug('fireguard-lyon'),
    );

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())
      ->method('findById')
      ->willReturn($organization);
    $organizationRepository->expects(self::once())
      ->method('save')
      ->with(self::callback(static function (Organization $saved): bool {
        return 'Fireguard Paris' === (string) $saved->name()
          && 'fireguard-paris' === (string) $saved->slug()
          && 'HQ in Paris' === $saved->description()
          && false === $saved->isActive();
      }));

    /** @var TransactionManagerPort&MockObject $transactionManager */
    $transactionManager = $this->createMock(TransactionManagerPort::class);
    $transactionManager->expects(self::once())
      ->method('transactional')
      ->with(self::isCallable())
      ->willReturnCallback(static fn (callable $operation): mixed => $operation());

    $handler = new UpdateOrganizationSettingsHandler(
      organizationRepository: $organizationRepository,
      transactionManager: $transactionManager,
    );

    $result = $handler->__invoke(new UpdateOrganizationSettingsCommand(
      organizationId: self::ORGANIZATION_ID,
      name: 'Fireguard Paris',
      slug: 'fireguard-paris',
      description: 'HQ in Paris',
      isActive: false,
    ));

    self::assertInstanceOf(UpdateOrganizationSettingsResult::class, $result);
    self::assertSame(self::ORGANIZATION_ID, $result->organizationId);
  }

  #[Test]
  public function testInvokeAppliesNotificationAndRegionalSections(): void
  {
    $organization = Organization::create(
      id: OrganizationId::fromString(self::ORGANIZATION_ID),
      name: new OrganizationName('Fireguard Lyon'),
      ownerUserId: self::OWNER_USER_ID,
      slug: new OrganizationSlug('fireguard-lyon'),
    );

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())
      ->method('findById')
      ->willReturn($organization);
    $organizationRepository->expects(self::once())
      ->method('save')
      ->with(self::callback(static function (Organization $saved): bool {
        $settings = $saved->settings();

        // Provided notification flags applied, unspecified ones preserved.
        return false === $settings->notifications->emailEnabled
          && true === $settings->notifications->inAppEnabled
          && 'Europe/Paris' === $settings->regional->timezone
          && 'fr-FR' === $settings->regional->locale
          // Unspecified regional fields keep their defaults.
          && 'metric' === $settings->regional->measurementSystem;
      }));

    /** @var TransactionManagerPort&MockObject $transactionManager */
    $transactionManager = $this->createMock(TransactionManagerPort::class);
    $transactionManager->expects(self::once())
      ->method('transactional')
      ->with(self::isCallable())
      ->willReturnCallback(static fn (callable $operation): mixed => $operation());

    $handler = new UpdateOrganizationSettingsHandler(
      organizationRepository: $organizationRepository,
      transactionManager: $transactionManager,
    );

    $result = $handler->__invoke(new UpdateOrganizationSettingsCommand(
      organizationId: self::ORGANIZATION_ID,
      notifications: ['email_enabled' => false, 'in_app_enabled' => null],
      regional: ['timezone' => 'Europe/Paris', 'locale' => 'fr-FR', 'date_format' => null],
    ));

    self::assertInstanceOf(UpdateOrganizationSettingsResult::class, $result);
  }

  #[Test]
  public function testInvokeThrowsWhenOrganizationNotFound(): void
  {
    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())
      ->method('findById')
      ->willReturn(null);
    $organizationRepository->expects(self::never())->method('save');

    /** @var TransactionManagerPort&MockObject $transactionManager */
    $transactionManager = $this->createMock(TransactionManagerPort::class);
    $transactionManager->expects(self::never())->method('transactional');

    $handler = new UpdateOrganizationSettingsHandler(
      organizationRepository: $organizationRepository,
      transactionManager: $transactionManager,
    );

    $this->expectException(OrganizationNotFoundException::class);

    $handler->__invoke(new UpdateOrganizationSettingsCommand(
      organizationId: self::ORGANIZATION_ID,
      name: 'Fireguard Paris',
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenSlugAlreadyExists(): void
  {
    $organization = Organization::create(
      id: OrganizationId::fromString(self::ORGANIZATION_ID),
      name: new OrganizationName('Fireguard Lyon'),
      ownerUserId: self::OWNER_USER_ID,
      slug: new OrganizationSlug('fireguard-lyon'),
    );

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())
      ->method('findById')
      ->willReturn($organization);

    $driverException = new class ('SQLSTATE[23505]: Unique violation: duplicate key value violates unique constraint "uniq_organization_slug"') extends RuntimeException implements DoctrineDriverException {
      public function getSQLState(): string
      {
        return '23505';
      }
    };

    $uniqueViolation = new UniqueConstraintViolationException($driverException, null);

    /** @var TransactionManagerPort&MockObject $transactionManager */
    $transactionManager = $this->createMock(TransactionManagerPort::class);
    $transactionManager->expects(self::once())
      ->method('transactional')
      ->with(self::isCallable())
      ->willThrowException(TransactionExecutionException::wrap($uniqueViolation));

    $handler = new UpdateOrganizationSettingsHandler(
      organizationRepository: $organizationRepository,
      transactionManager: $transactionManager,
    );

    $this->expectException(OrganizationSlugAlreadyExistsException::class);
    $this->expectExceptionMessage('Organization slug "fireguard-paris" already exists.');

    $handler->__invoke(new UpdateOrganizationSettingsCommand(
      organizationId: self::ORGANIZATION_ID,
      slug: 'fireguard-paris',
    ));
  }
}
