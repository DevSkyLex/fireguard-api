<?php

declare(strict_types=1);

namespace Tests\Unit\Import\Application\UseCase\Query\ListImportJobs;

use Import\Application\Port\Outbound\ImportJobRepositoryPort;
use Import\Application\UseCase\Query\ListImportJobs\{ListImportJobsHandler, ListImportJobsQuery};
use Import\Domain\Model\ImportJob\ImportJob;
use Import\Domain\ValueObject\{ImportJobId, ImportKind};
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Domain\Exception\OrganizationAccessDeniedException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ListImportJobsHandler.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ListImportJobsHandler::class)]
final class ListImportJobsHandlerTest extends TestCase
{
  private const string JOB_ID = '018f0b68-6758-7a12-8a1d-3f0d97f65a01';

  private const string ORGANIZATION_ID = 'org-1';

  private const string USER_ID = 'user-1';

  #[Test]
  public function itReturnsTheOrgScopedPageWhenNoKindFilterIsGiven(): void
  {
    $repository = $this->createStub(ImportJobRepositoryPort::class);
    $repository->method('listByOrganization')->willReturn([$this->equipmentJob()]);
    $repository->method('countByOrganization')->willReturn(1);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $handler = new ListImportJobsHandler($repository, $authorization);

    $result = $handler->__invoke(new ListImportJobsQuery(self::USER_ID, self::ORGANIZATION_ID));

    self::assertCount(1, $result->items);
    self::assertSame(self::JOB_ID, $result->items[0]->importJobId);
    self::assertSame(1, $result->page);
    self::assertSame(30, $result->itemsPerPage);
    self::assertSame(1, $result->total);
  }

  #[Test]
  public function itAssertsTheKindReadPermissionWhenFiltering(): void
  {
    $repository = $this->createStub(ImportJobRepositoryPort::class);
    $repository->method('listByOrganization')->willReturn([]);
    $repository->method('countByOrganization')->willReturn(0);

    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('assertGrantedPermissions')
      ->with(self::USER_ID, self::ORGANIZATION_ID, ['organization.equipment.read']);

    $handler = new ListImportJobsHandler($repository, $authorization);

    $result = $handler->__invoke(new ListImportJobsQuery(self::USER_ID, self::ORGANIZATION_ID, kind: 'equipment'));

    self::assertSame([], $result->items);
    self::assertSame(0, $result->total);
  }

  #[Test]
  public function itNormalizesNonPositivePagingParameters(): void
  {
    $repository = $this->createStub(ImportJobRepositoryPort::class);
    $repository->method('listByOrganization')->willReturn([]);
    $repository->method('countByOrganization')->willReturn(0);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $handler = new ListImportJobsHandler($repository, $authorization);

    $result = $handler->__invoke(new ListImportJobsQuery(
      self::USER_ID,
      self::ORGANIZATION_ID,
      kind: null,
      page: 0,
      itemsPerPage: 0,
    ));

    self::assertSame(0, $result->page);
    self::assertSame(1, $result->itemsPerPage);
  }

  #[Test]
  public function itRejectsAnUnknownKindFilter(): void
  {
    $handler = new ListImportJobsHandler(
      $this->createStub(ImportJobRepositoryPort::class),
      $this->createStub(OrganizationAuthorizationPort::class),
    );

    $this->expectException(InvalidArgumentException::class);

    $handler->__invoke(new ListImportJobsQuery(self::USER_ID, self::ORGANIZATION_ID, kind: 'unknown'));
  }

  #[Test]
  public function itDeniesAccessWhenNeitherReadPermissionIsHeld(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(false);

    $handler = new ListImportJobsHandler($this->createStub(ImportJobRepositoryPort::class), $authorization);

    $this->expectException(OrganizationAccessDeniedException::class);

    $handler->__invoke(new ListImportJobsQuery(self::USER_ID, self::ORGANIZATION_ID));
  }

  private function equipmentJob(): ImportJob
  {
    return ImportJob::create(
      id: ImportJobId::fromString(self::JOB_ID),
      organizationId: self::ORGANIZATION_ID,
      kind: ImportKind::EQUIPMENT,
      storagePath: 'imports/org-1/job.csv',
      originalFilename: 'equipment.csv',
      createdBy: self::USER_ID,
    );
  }
}
