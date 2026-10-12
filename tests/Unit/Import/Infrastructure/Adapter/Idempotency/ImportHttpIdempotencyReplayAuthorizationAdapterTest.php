<?php

declare(strict_types=1);

namespace Tests\Unit\Import\Infrastructure\Adapter\Idempotency;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Import\Application\Service\ImportKindProcessorRegistry;
use Import\Application\UseCase\Command\CreateImportJob\CreateImportJobResult;
use Import\Domain\Exception\{ImportAccessDeniedException, ImportJobNotFoundException};
use Import\Infrastructure\Adapter\Idempotency\{ImportHttpIdempotencyOperationContributionAdapter, ImportHttpIdempotencyReplayAuthorizationAdapter};
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Idempotency\IdempotencyDeniedException;
use Shared\Infrastructure\Idempotency\HttpIdempotencyResultCodec;
use Tests\Support\Idempotency\OwnerReplayTestSupport;
use Tests\Support\Import\RecordingImportKindProcessor;

/** @category Unit Test */
final class ImportHttpIdempotencyReplayAuthorizationAdapterTest extends TestCase
{
  use OwnerReplayTestSupport;

  public function testIndependentRegisteredKindUsesItsFreshDeclaredPermissionOnReplay(): void
  {
    $permissions = ['organization.test_assets.write'];
    [$ordinary, $organizations, $evidence] = $this->authorities($permissions);
    $jobOrganization = self::ORGANIZATION;
    $connection = $this->createStub(Connection::class);
    $connection->method('fetchAssociative')->willReturnCallback(static function () use (&$jobOrganization): array {
      return ['organization_id' => $jobOrganization, 'kind' => 'test_asset', 'status' => 'completed'];
    });
    $registry = new ImportKindProcessorRegistry([new RecordingImportKindProcessor()]);
    $gate = new ImportHttpIdempotencyReplayAuthorizationAdapter($ordinary, $organizations, $registry, $connection, $evidence);
    $request = $this->request($this->operation(ImportHttpIdempotencyOperationContributionAdapter::definitions(), 'resume_import_job'), ['id' => self::TARGET]);
    self::assertNull($gate->scope($request)->organizationId);
    $proof = $this->proof($request, $gate->authorize($request, null), self::TARGET, $permissions);
    self::assertSame('test_asset', $gate->authorize($request, $proof)['importKind']);
    $permissions = ['organization.equipment.write'];
    $this->expectException(ImportAccessDeniedException::class);
    $gate->authorize($request, $proof);
  }

  public function testMissingContributionCannotReplayEvenWithTheFormerPermission(): void
  {
    $permissions = ['organization.test_assets.write'];
    [$ordinary, $organizations, $evidence] = $this->authorities($permissions);
    $connection = $this->createStub(Connection::class);
    $gate = new ImportHttpIdempotencyReplayAuthorizationAdapter($ordinary, $organizations, new ImportKindProcessorRegistry([]), $connection, $evidence);
    $request = $this->request($this->operation(ImportHttpIdempotencyOperationContributionAdapter::definitions(), '_api_/imports_post'), [], ['kind' => 'multipart', 'fields' => ['organization' => '/api/organizations/' . self::ORGANIZATION, 'kind' => 'test_asset']]);
    $this->expectException(IdempotencyDeniedException::class);
    $gate->authorize($request, null);
  }

  public function testConfirmationChecksTheCreatedChildRatherThanOnlyTheSourceSimulation(): void
  {
    $permissions = ['organization.test_assets.write'];
    [$ordinary, $organizations, $evidence] = $this->authorities($permissions);
    $connection = $this->createStub(Connection::class);
    $connection->method('fetchAssociative')->willReturnCallback(static fn (string $sql, array $parameters): array => ['organization_id' => self::CHILD === $parameters['id'] ? self::RELATED : self::ORGANIZATION, 'kind' => 'test_asset', 'status' => 'completed']);
    $gate = new ImportHttpIdempotencyReplayAuthorizationAdapter($ordinary, $organizations, new ImportKindProcessorRegistry([new RecordingImportKindProcessor()]), $connection, $evidence);
    $request = $this->request($this->operation(ImportHttpIdempotencyOperationContributionAdapter::definitions(), 'confirm_import_simulation'), ['id' => self::TARGET]);
    $proof = $this->proof($request, $gate->authorize($request, null), self::CHILD, $permissions);
    $this->expectException(ImportJobNotFoundException::class);
    $gate->authorize($request, $proof);
  }

  public function testCreateManifestUsesFinalRootOwnershipAndKeepsTheOriginalPendingSnapshot(): void
  {
    $operation = $this->operation(ImportHttpIdempotencyOperationContributionAdapter::definitions(), '_api_/imports_post');
    self::assertSame('main', $operation->database);
    self::assertSame('root_owner', $operation->transactionMode);
    $codec = new HttpIdempotencyResultCodec();
    $original = new CreateImportJobResult(self::TARGET, self::ORGANIZATION, 'test_asset', 'pending', 'original.csv', new DateTimeImmutable('2026-01-01'), true);
    $restored = $codec->decode($codec->encode($original, $operation), $operation);
    self::assertInstanceOf(CreateImportJobResult::class, $restored);
    self::assertEquals($original, $restored);
    self::assertSame('pending', $restored->status);
  }
}
