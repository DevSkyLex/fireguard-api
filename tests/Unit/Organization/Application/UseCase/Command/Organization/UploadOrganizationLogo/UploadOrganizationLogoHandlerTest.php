<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Command\Organization\UploadOrganizationLogo;

use Organization\Application\Contract\Exception\OrganizationAccessDeniedException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\Port\Outbound\Internal\OrganizationRepositoryPort;
use Organization\Application\Port\Outbound\OrganizationLogoImagePort;
use Organization\Application\UseCase\Command\Organization\UploadOrganizationLogo\{UploadOrganizationLogoCommand, UploadOrganizationLogoHandler};
use Organization\Domain\Model\Organization\{Organization, OrganizationCreationOptions};
use Organization\Domain\ValueObject\{OrganizationId, OrganizationName};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Contract\Transaction\{CommitOutcomeUnknownException, TransactionAlreadyActiveException};
use Shared\Application\Port\Outbound\{EventDispatcherPort, FileCleanupPort, FileStoragePort, LoggerPort, RootTransactionManagerPort, UuidGeneratorPort};

#[CoversClass(UploadOrganizationLogoHandler::class)]
final class UploadOrganizationLogoHandlerTest extends TestCase
{
  private const string ORGANIZATION = '550e8400-e29b-41d4-a716-446655440010';

  private const string ATTEMPT = 'bd000000-0000-4000-8000-000000000086';

  private const string PATH = 'organization-logos/' . self::ORGANIZATION . '/' . self::ATTEMPT . '.webp';

  #[Test]
  public function aSuccessfulUploadKeepsItsOwnKeyAndDefersDeletionOfThePreviousLogo(): void
  {
    $organizations = $this->organizations();
    $organizations->expects(self::once())->method('save');
    $images = $this->createMock(OrganizationLogoImagePort::class);
    $images->expects(self::once())->method('encode')->with('source')->willReturn('encoded');
    $files = $this->createMock(FileStoragePort::class);
    $files->expects(self::once())->method('write')->with(self::PATH, 'encoded');
    $files->expects(self::never())->method('delete');
    $cleanup = $this->createMock(FileCleanupPort::class);
    $cleanup->expects(self::once())->method('request')->with('organization-logos/' . self::ORGANIZATION . '/logo.webp');
    $result = $this->handler($organizations, $images, $files, $cleanup)($this->command());
    self::assertSame('https://app.test/api/organizations/' . self::ORGANIZATION . '/logo.webp?v=' . self::ATTEMPT, $result->logoUrl);
  }

  #[Test]
  public function aLateSaveFailureCompensatesOnlyTheCurrentAttempt(): void
  {
    $failure = new RuntimeException('SQL failed');
    $organizations = $this->organizations();
    $organizations->expects(self::once())->method('save')->willThrowException($failure);
    $files = $this->createMock(FileStoragePort::class);
    $files->expects(self::once())->method('write')->with(self::PATH, 'encoded');
    $files->expects(self::once())->method('exists')->with(self::PATH)->willReturn(true);
    $files->expects(self::once())->method('delete')->with(self::PATH);
    $cleanup = $this->createMock(FileCleanupPort::class);
    $cleanup->expects(self::never())->method('request');
    $this->expectExceptionObject($failure);
    $this->handler($organizations, $this->images(), $files, $cleanup)($this->command());
  }

  #[Test]
  public function compensationFailureDoesNotMaskTheOriginalSqlFailure(): void
  {
    $failure = new RuntimeException('SQL failed');
    $organizations = $this->organizations();
    $organizations->method('save')->willThrowException($failure);
    $files = $this->createMock(FileStoragePort::class);
    $files->method('exists')->willReturn(true);
    $files->expects(self::once())->method('delete')->with(self::PATH)->willThrowException(new RuntimeException('Storage unavailable'));
    $logger = $this->createMock(LoggerPort::class);
    $logger->expects(self::once())->method('warning')->willThrowException(new RuntimeException('Logging unavailable'));
    $this->expectExceptionObject($failure);
    $this->handler($organizations, $this->images(), $files, $this->createStub(FileCleanupPort::class), logger: $logger)($this->command());
  }

  #[Test]
  public function anUncertainCommitRetainsTheAttemptForRecovery(): void
  {
    $transactions = $this->createMock(RootTransactionManagerPort::class);
    $failure = new CommitOutcomeUnknownException(new RuntimeException('Connection lost during commit'));
    $transactions->expects(self::once())->method('transactional')->willReturnCallback(static function (callable $work) use ($failure): never {
      $work();

      throw $failure;
    });
    $files = $this->createMock(FileStoragePort::class);
    $files->expects(self::once())->method('write');
    $files->expects(self::never())->method('exists');
    $files->expects(self::never())->method('delete');
    $this->expectExceptionObject($failure);
    $this->handler($this->organizations(), $this->images(), $files, $this->createStub(FileCleanupPort::class), transactions: $transactions)($this->command());
  }

  #[Test]
  public function anOuterTransactionIsRejectedBeforeDecodingOrStorageWrites(): void
  {
    $transactions = $this->createMock(RootTransactionManagerPort::class);
    $transactions->expects(self::once())->method('assertCanStart')->willThrowException(new TransactionAlreadyActiveException());
    $transactions->expects(self::never())->method('transactional');
    $images = $this->createMock(OrganizationLogoImagePort::class);
    $images->expects(self::never())->method('encode');
    $files = $this->createMock(FileStoragePort::class);
    $files->expects(self::never())->method('write');
    $this->expectException(TransactionAlreadyActiveException::class);
    $this->handler($this->organizations(false), $images, $files, $this->createStub(FileCleanupPort::class), transactions: $transactions)($this->command());
  }

  #[Test]
  public function aProgrammaticCallerMustHaveWritePermission(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(false);
    $transactions = $this->createMock(RootTransactionManagerPort::class);
    $transactions->expects(self::never())->method('assertCanStart');
    $this->expectException(OrganizationAccessDeniedException::class);
    $this->handler($this->organizations(false), $this->images(), $this->createStub(FileStoragePort::class), $this->createStub(FileCleanupPort::class), transactions: $transactions, authorization: $authorization)($this->command());
  }

  private function handler(OrganizationRepositoryPort $organizations, OrganizationLogoImagePort $images, FileStoragePort $files, FileCleanupPort $cleanup, ?RootTransactionManagerPort $transactions = null, ?LoggerPort $logger = null, ?OrganizationAuthorizationPort $authorization = null): UploadOrganizationLogoHandler
  {
    if (null === $transactions) {
      $transactions = $this->createStub(RootTransactionManagerPort::class);
      $transactions->method('transactional')->willReturnCallback(static fn (callable $work): mixed => $work());
    }
    if (null === $authorization) {
      $authorization = $this->createStub(OrganizationAuthorizationPort::class);
      $authorization->method('hasPermission')->willReturn(true);
    }
    $ids = $this->createStub(UuidGeneratorPort::class);
    $ids->method('generate')->willReturn(self::ATTEMPT);

    return new UploadOrganizationLogoHandler($organizations, $authorization, $images, $files, $cleanup, $transactions, $this->createStub(EventDispatcherPort::class), $ids, $logger ?? $this->createStub(LoggerPort::class));
  }

  private function organizations(bool $willRead = true): OrganizationRepositoryPort&\PHPUnit\Framework\MockObject\MockObject
  {
    $organizations = $this->createMock(OrganizationRepositoryPort::class);
    $organizations->expects($willRead ? self::once() : self::never())->method('findByIdForUpdate')->willReturn(Organization::create(new OrganizationId(self::ORGANIZATION), new OrganizationName('Original'), 'owner', new OrganizationCreationOptions(logoUrl: 'https://app.test/logo.webp?v=1726000000')));

    return $organizations;
  }

  private function images(): OrganizationLogoImagePort
  {
    $images = $this->createStub(OrganizationLogoImagePort::class);
    $images->method('encode')->willReturn('encoded');

    return $images;
  }

  private function command(): UploadOrganizationLogoCommand
  {
    return new UploadOrganizationLogoCommand(self::ORGANIZATION, 'owner', 'source', 'https://app.test/');
  }
}
