<?php

declare(strict_types=1);

namespace Tests\Unit\Onboarding\Application\Service;

use Onboarding\Application\Contract\Setup\{OrganizationSetupConflict, OrganizationSetupContext, OrganizationSetupOperation, OrganizationSetupSession};
use Onboarding\Application\Port\Outbound\OrganizationSetupRepositoryPort;
use Onboarding\Application\Service\OrganizationSetupService;
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\TransactionManagerPort;

/**
 * Profile preservation in prepared setup receipts.
 *
 * @category Test
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationSetupOperatingProfileTest extends TestCase
{
  #[Test]
  public function omittedProfileIsPreparedAsOperator(): void
  {
    $repository = $this->createMock(OrganizationSetupRepositoryPort::class);
    $repository->method('findSession')->with('user', true)->willReturn(new OrganizationSetupSession('session', 'user', 'in_progress', 'create_organization', null, true, []));
    $repository->expects(self::once())->method('saveOperations')->with('session', self::callback(static function (array $operations): bool {
      self::assertCount(1, $operations);
      self::assertInstanceOf(OrganizationSetupOperation::class, $operations[0]);
      self::assertSame(['name' => 'Company', 'operatingProfile' => 'operator', 'slug' => null], $operations[0]->payload);

      return true;
    }));

    $this->service($repository)->prepare('user', 'session', 'create_organization', [['itemKey' => 'org', 'payload' => ['name' => 'Company']]]);
  }

  #[Test]
  public function preparedProviderProfileIsPreservedOnReplayAfterConfirmation(): void
  {
    $operation = new OrganizationSetupOperation('create_organization', 'org', ['name' => 'Company', 'operatingProfile' => 'service_provider', 'slug' => null], 'created-id');
    $repository = $this->createMock(OrganizationSetupRepositoryPort::class);
    $repository->method('findSession')->willReturn(new OrganizationSetupSession('session', 'user', 'in_progress', 'select_plan', 'created-id', true, [$operation]));
    $repository->expects(self::never())->method('saveOperations');

    $replayed = $this->service($repository)->begin(new OrganizationSetupContext('user', 'session', 'org'), 'create_organization', null, ['operatingProfile' => 'service_provider', 'name' => 'Company']);

    self::assertSame($operation, $replayed);
    self::assertSame('service_provider', $replayed->payload['operatingProfile']);
    self::assertSame('created-id', $replayed->resourceId);
  }

  #[Test]
  public function legacyReceiptCanReplayWithExplicitOperatorDefault(): void
  {
    $operation = new OrganizationSetupOperation('create_organization', 'org', ['name' => 'Company'], 'created-id');
    $repository = $this->createStub(OrganizationSetupRepositoryPort::class);
    $repository->method('findSession')->willReturn(new OrganizationSetupSession('session', 'user', 'in_progress', 'select_plan', 'created-id', true, [$operation]));

    $replayed = $this->service($repository)->begin(new OrganizationSetupContext('user', 'session', 'org'), 'create_organization', null, ['name' => 'Company', 'operatingProfile' => 'operator']);

    self::assertSame('created-id', $replayed->resourceId);
  }

  #[Test]
  public function replayCannotReplacePreparedProviderWithImplicitOperator(): void
  {
    $operation = new OrganizationSetupOperation('create_organization', 'org', ['name' => 'Company', 'operatingProfile' => 'service_provider'], 'created-id');
    $repository = $this->createMock(OrganizationSetupRepositoryPort::class);
    $repository->method('findSession')->willReturn(new OrganizationSetupSession('session', 'user', 'in_progress', 'select_plan', 'created-id', true, [$operation]));
    $repository->expects(self::never())->method('saveOperations');

    $this->expectException(OrganizationSetupConflict::class);
    $this->service($repository)->begin(new OrganizationSetupContext('user', 'session', 'org'), 'create_organization', null, ['name' => 'Company']);
  }

  #[Test]
  public function completedKeyCannotBeRepreparedWithAnotherProfile(): void
  {
    $operation = new OrganizationSetupOperation('create_organization', 'org', ['name' => 'Company', 'operatingProfile' => 'service_provider'], 'created-id');
    $repository = $this->createMock(OrganizationSetupRepositoryPort::class);
    $repository->method('findSession')->willReturn(new OrganizationSetupSession('session', 'user', 'in_progress', 'select_plan', 'created-id', true, [$operation]));
    $repository->expects(self::never())->method('saveOperations');

    $this->expectException(OrganizationSetupConflict::class);
    $this->service($repository)->prepare('user', 'session', 'create_organization', [['itemKey' => 'org', 'payload' => ['name' => 'Company', 'operatingProfile' => 'operator']]]);
  }

  #[Test]
  #[DataProvider('invalidProfiles')]
  public function invalidProfileCannotBePrepared(mixed $profile): void
  {
    $repository = $this->createMock(OrganizationSetupRepositoryPort::class);
    $repository->method('findSession')->willReturn(new OrganizationSetupSession('session', 'user', 'in_progress', 'create_organization', null, true, []));
    $repository->expects(self::never())->method('saveOperations');

    $this->expectException(OrganizationSetupConflict::class);
    $this->service($repository)->prepare('user', 'session', 'create_organization', [['itemKey' => 'org', 'payload' => ['name' => 'Company', 'operatingProfile' => $profile]]]);
  }

  /**
   * @return iterable<string,array{mixed}>
   */
  public static function invalidProfiles(): iterable
  {
    yield 'null' => [null];
    yield 'unknown' => ['administrator'];
    yield 'empty' => [''];
    yield 'number' => [1];
    yield 'object' => [['value' => 'operator']];
  }

  private function service(OrganizationSetupRepositoryPort $repository): OrganizationSetupService
  {
    $transactions = $this->createStub(TransactionManagerPort::class);
    $transactions->method('transactional')->willReturnCallback(static fn (callable $work): mixed => $work());

    return new OrganizationSetupService($repository, $transactions);
  }
}
