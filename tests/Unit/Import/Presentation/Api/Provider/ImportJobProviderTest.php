<?php

declare(strict_types=1);

namespace Tests\Unit\Import\Presentation\Api\Provider;

use ApiPlatform\Metadata\Get;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Import\Application\UseCase\Query\GetImportJob\{GetImportJobQuery, GetImportJobResult};
use Import\Domain\Exception\ImportJobNotFoundException;
use Import\Presentation\Api\Factory\ImportJobOutputFactory;
use Import\Presentation\Api\Provider\ImportJobProvider;
use InvalidArgumentException;
use Organization\Domain\Exception\OrganizationAccessDeniedException;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{
  AccessDeniedHttpException,
  BadRequestHttpException,
  NotFoundHttpException
};
use Throwable;

/**
 * Test ImportJobProviderTest.
 *
 * An import job's error report names the rows a user's upload failed on, so
 * the endpoint must refuse anyone who is not entitled to it and surface a
 * missing job as a 404 rather than leaking a stack trace.
 *
 * @category Provider Tests
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ImportJobProvider::class)]
final class ImportJobProviderTest extends TestCase
{
  // #region Constants
  private const string JOB_ID = '550e8400-e29b-41d4-a716-446655483001';

  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655483002';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655483003';
  // #endregion

  // #region Methods
  /**
   * @return iterable<string, array{Throwable, class-string<Throwable>}>
   */
  public static function domainFailureProvider(): iterable
  {
    yield 'unknown job' => [
      ImportJobNotFoundException::withId(self::JOB_ID),
      NotFoundHttpException::class,
    ];
    yield 'not entitled' => [
      new OrganizationAccessDeniedException('Not a member of the organization.'),
      AccessDeniedHttpException::class,
    ];
    yield 'invalid argument' => [
      new InvalidArgumentException('Malformed job id.'),
      BadRequestHttpException::class,
    ];
  }

  #[Test]
  public function testProvideReturnsTheMappedImportJob(): void
  {
    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static fn (GetImportJobQuery $query): bool => self::USER_ID === $query->userId
        && self::JOB_ID === $query->importJobId))
      ->willReturn($this->jobResult());

    $output = $this->createProvider($queryBus)->provide(new Get(), ['id' => self::JOB_ID]);

    self::assertSame(self::JOB_ID, $output->id);
    self::assertSame('/api/organizations/' . self::ORGANIZATION_ID, $output->organization);
    self::assertSame('equipment', $output->kind);
    self::assertSame('completed', $output->status);
    self::assertSame(10, $output->totalRows);
    self::assertSame(8, $output->successfulRows);
    self::assertSame(2, $output->failedRows);
  }

  #[Test]
  public function testProvideThrowsWhenNoUserIsAuthenticated(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    $provider = new ImportJobProvider(
      queryBus: $queryBus,
      outputFactory: new ImportJobOutputFactory(),
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);
    $this->expectExceptionMessage('Authentication required.');

    $provider->provide(new Get(), ['id' => self::JOB_ID]);
  }

  #[Test]
  public function testProvideThrowsWhenTheIdIsMissing(): void
  {
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    $this->expectException(BadRequestHttpException::class);

    $this->createProvider($queryBus)->provide(new Get(), []);
  }

  #[Test]
  public function testProvideThrowsWhenTheIdIsBlank(): void
  {
    $this->expectException(BadRequestHttpException::class);

    $this->createProvider($this->createStub(QueryBusPort::class))->provide(new Get(), ['id' => '']);
  }

  /**
   * @param class-string<Throwable> $expected
   */
  #[Test]
  #[DataProvider('domainFailureProvider')]
  public function testProvideMapsEachDomainFailureToItsHttpStatus(Throwable $failure, string $expected): void
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException($failure);

    $this->expectException($expected);

    $this->createProvider($queryBus)->provide(new Get(), ['id' => self::JOB_ID]);
  }

  #[Test]
  public function testProvideRethrowsAnUnrecognisedFailure(): void
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(new RuntimeException('database is down'));

    $this->expectException(RuntimeException::class);

    $this->createProvider($queryBus)->provide(new Get(), ['id' => self::JOB_ID]);
  }

  private function createProvider(QueryBusPort $queryBus): ImportJobProvider
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(new SecurityUser(
      id: self::USER_ID,
      email: 'user@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
      scopes: [],
      isActive: true,
    ));

    return new ImportJobProvider(
      queryBus: $queryBus,
      outputFactory: new ImportJobOutputFactory(),
      security: $security,
    );
  }

  private function jobResult(): GetImportJobResult
  {
    return new GetImportJobResult(
      importJobId: self::JOB_ID,
      organizationId: self::ORGANIZATION_ID,
      kind: 'equipment',
      status: 'completed',
      originalFilename: 'equipment.csv',
      dryRun: false,
      totalRows: 10,
      processedRows: 10,
      successfulRows: 8,
      failedRows: 2,
      errorReport: [],
      jobError: null,
      createdBy: self::USER_ID,
      createdAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      startedAt: new DateTimeImmutable('2026-01-01T00:01:00+00:00'),
      completedAt: new DateTimeImmutable('2026-01-01T00:05:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-01-01T00:05:00+00:00'),
    );
  }
  // #endregion
}
