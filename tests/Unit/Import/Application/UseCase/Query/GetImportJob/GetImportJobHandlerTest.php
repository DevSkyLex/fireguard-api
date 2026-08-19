<?php

declare(strict_types=1);

namespace Tests\Unit\Import\Application\UseCase\Query\GetImportJob;

use Import\Application\Port\Outbound\ImportJobRepositoryPort;
use Import\Application\UseCase\Query\GetImportJob\{GetImportJobHandler, GetImportJobQuery};
use Import\Domain\Exception\{ImportAccessDeniedException, ImportJobNotFoundException};
use Import\Domain\Model\ImportJob\ImportJob;
use Import\Domain\ValueObject\{ImportJobId, ImportKind};
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test GetImportJobHandler.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetImportJobHandler::class)]
final class GetImportJobHandlerTest extends TestCase
{
  private const string JOB_ID = '018f0b68-6758-7a12-8a1d-3f0d97f65a01';

  private const string ORGANIZATION_ID = 'org-1';

  private const string USER_ID = 'user-1';

  #[Test]
  public function itReturnsTheReadViewAndAssertsTheKindReadPermission(): void
  {
    $repository = $this->createStub(ImportJobRepositoryPort::class);
    $repository->method('findById')->willReturn($this->facilityJob());

    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('resolveAccess')
      ->with(self::USER_ID, self::ORGANIZATION_ID, 'organization.facilities.read')
      ->willReturn(OrganizationAccessDecision::GRANTED);

    $handler = new GetImportJobHandler($repository, $authorization);

    $result = $handler->__invoke(new GetImportJobQuery(self::USER_ID, self::JOB_ID));

    self::assertSame(self::JOB_ID, $result->importJobId);
    self::assertSame('facility', $result->kind);
    self::assertSame(self::ORGANIZATION_ID, $result->organizationId);
  }

  #[Test]
  public function itAnswersNotFoundWhenTheJobBelongsToAnotherOrganization(): void
  {
    // The 403 an unentitled member gets would confirm the id exists to a
    // caller who is not a member of the owning organization at all.
    $repository = $this->createStub(ImportJobRepositoryPort::class);
    $repository->method('findById')->willReturn($this->facilityJob());

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::OUTSIDE_SCOPE);

    $handler = new GetImportJobHandler($repository, $authorization);

    $this->expectException(ImportJobNotFoundException::class);
    $this->expectExceptionMessage(self::JOB_ID);

    $handler->__invoke(new GetImportJobQuery(self::USER_ID, self::JOB_ID));
  }

  #[Test]
  public function itDeniesAMemberLackingTheKindReadPermission(): void
  {
    $repository = $this->createStub(ImportJobRepositoryPort::class);
    $repository->method('findById')->willReturn($this->facilityJob());

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::MISSING_PERMISSION);

    $handler = new GetImportJobHandler($repository, $authorization);

    $this->expectException(ImportAccessDeniedException::class);
    $this->expectExceptionMessage('organization.facilities.read');

    $handler->__invoke(new GetImportJobQuery(self::USER_ID, self::JOB_ID));
  }

  #[Test]
  public function itThrowsWhenTheJobDoesNotExist(): void
  {
    $repository = $this->createStub(ImportJobRepositoryPort::class);
    $repository->method('findById')->willReturn(null);

    $handler = new GetImportJobHandler($repository, $this->createStub(OrganizationAuthorizationPort::class));

    $this->expectException(ImportJobNotFoundException::class);

    $handler->__invoke(new GetImportJobQuery(self::USER_ID, self::JOB_ID));
  }

  private function facilityJob(): ImportJob
  {
    return ImportJob::create(
      id: ImportJobId::fromString(self::JOB_ID),
      organizationId: self::ORGANIZATION_ID,
      kind: ImportKind::FACILITY,
      storagePath: 'imports/org-1/job.csv',
      originalFilename: 'facilities.csv',
      createdBy: self::USER_ID,
    );
  }
}
