<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\Service;

use DateTimeImmutable;
use InvalidArgumentException;
use LogicException;
use Organization\Application\Contract\Authorization\{OrganizationPermissionDefinition, OrganizationSystemRoleDefinition};
use Organization\Application\Contract\Event\OrganizationSettingsUpdatedEvent;
use Organization\Application\Contract\Quota\{OrganizationQuotaDefinition, OrganizationQuotaExceededException};
use Organization\Application\Contract\Settings\{OrganizationSettingsPolicyDefinition, OrganizationSettingsValueType};
use Organization\Application\Port\Outbound\{EquipmentTypeCatalogPort, OrganizationPermissionContributionPort, OrganizationQuotaContributionPort, OrganizationSettingsPolicyContributionPort, OrganizationSystemRoleContributionPort};
use Organization\Application\Port\Outbound\Internal\{OrganizationMemberRepositoryPort, OrganizationQuotaLockPort, OrganizationRepositoryPort, PlanRepositoryPort};
use Organization\Application\Service\{OrganizationPermissionRegistry, OrganizationQuotaRegistry, OrganizationQuotaService, OrganizationSettingsPolicyRegistry, OrganizationSystemRoleRegistry};
use Organization\Application\UseCase\Command\Organization\UpdateOrganizationSettings\{UpdateOrganizationSettingsCommand, UpdateOrganizationSettingsHandler};
use Organization\Domain\Model\Organization\{Organization, OrganizationCreationOptions};
use Organization\Domain\Model\Plan\Plan;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationName, OrganizationSettings, PlanId, PlanKey};
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\{EventDispatcherPort, TransactionManagerPort};
use Shared\Domain\Exception\InvalidValueException;

/**
 * Class OrganizationCatalogContributionTest
 *
 * Proves the Organization engines consume an unrelated owner without editing their resource or policy lists.
 *
 * @category Test
 */
final class OrganizationCatalogContributionTest extends TestCase
{
  private const string ORGANIZATION_ID = '11111111-1111-4111-8111-111111111111';

  private const string PLAN_ID = '22222222-2222-4222-8222-222222222222';

  public function testHistoricalAliasesResolveAuthorityWithoutEnteringNewGrantVocabulary(): void
  {
    $catalog = \Tests\Support\OrganizationCatalogTestFactory::permissions();
    self::assertSame('organization.facilities.read', $catalog->canonicalName('facility.read'));
    self::assertSame('organization.facilities.read', $catalog->canonicalName('organization.facility.read'));
    self::assertSame('organization.*', $catalog->canonicalName('*'));
    self::assertFalse($catalog->contains('facility.read'));
    self::assertNull($catalog->canonicalName('retired.unknown.read'));
    $this->expectException(InvalidValueException::class);
    $catalog->assertKnown(['facility.read']);
  }

  public function testFakeQuotaParticipatesInPlanValidationUsageAndEnforcement(): void
  {
    $catalog = new OrganizationQuotaRegistry([$this->quotaContribution()]);
    self::assertSame(['widgets' => 2], $catalog->normalizeLimits(['widgets' => 2]));
    self::assertSame('Up to 2 widgets', $catalog->get('widgets')->summarize(2));
    $plan = Plan::create(PlanId::fromString(self::PLAN_ID), new PlanKey('test'), 'Test plan', ['widgets' => 2]);
    $organization = Organization::create(OrganizationId::fromString(self::ORGANIZATION_ID), new OrganizationName('Test organization'), 'owner', new OrganizationCreationOptions(planId: $plan->id()));
    $organizations = $this->createStub(OrganizationRepositoryPort::class);
    $organizations->method('findById')->willReturn($organization);
    $plans = $this->createStub(PlanRepositoryPort::class);
    $plans->method('findById')->willReturn($plan);
    $members = $this->createStub(OrganizationMemberRepositoryPort::class);
    $lock = $this->createMock(OrganizationQuotaLockPort::class);
    $lock->expects(self::once())->method('acquire')->with(self::ORGANIZATION_ID, 'widgets');
    $quota = new OrganizationQuotaService($organizations, $plans, $members, $lock, $catalog);
    self::assertSame([['resource' => 'widgets', 'used' => 2, 'limit' => 2]], $quota->getQuotaSummary(self::ORGANIZATION_ID));
    $this->expectException(OrganizationQuotaExceededException::class);
    $quota->assertCanAdd(self::ORGANIZATION_ID, 'widgets');
  }

  public function testUnknownQuotaCannotBecomeUnlimitedOrBypassAnEmptyBatch(): void
  {
    $catalog = new OrganizationQuotaRegistry([]);
    $organizations = $this->createMock(OrganizationRepositoryPort::class);
    $organizations->expects(self::never())->method('findById');
    $quota = new OrganizationQuotaService($organizations, $this->createStub(PlanRepositoryPort::class), $this->createStub(OrganizationMemberRepositoryPort::class), $this->createStub(OrganizationQuotaLockPort::class), $catalog);
    $this->expectException(InvalidValueException::class);
    $quota->assertCanAddMultiple(self::ORGANIZATION_ID, 'unregistered', 0);
  }

  public function testUnknownNewPlanQuotaIsRejected(): void
  {
    $this->expectException(InvalidValueException::class);
    new OrganizationQuotaRegistry([$this->quotaContribution()])->normalizeLimits(['removed' => 2]);
  }

  /**
   * @param array<array-key, mixed> $limits malformed transport keys
   */
  #[DataProvider('numericQuotaKeys')]
  public function testNumericQuotaKeysProduceAValidationFailure(array $limits): void
  {
    $this->expectException(InvalidValueException::class);
    $this->expectExceptionMessage('Quota resource keys must be registered string identifiers.');
    new OrganizationQuotaRegistry([$this->quotaContribution()])->normalizeLimits($limits);
  }

  /**
   * @return iterable<string, array{array<array-key, mixed>}>
   */
  public static function numericQuotaKeys(): iterable
  {
    yield 'JSON list' => [[5]];
    yield 'numeric JSON object key' => [['0' => 5]];
    yield 'mixed named and numeric keys' => [['widgets' => 2, 0 => 5]];
  }

  public function testRetiredQuotaHydratesWithoutAnyInstalledContribution(): void
  {
    $now = new DateTimeImmutable('2026-01-01');
    $plan = Plan::reconstitute(PlanId::fromString(self::PLAN_ID), new PlanKey('legacy'), 'Legacy plan', ['retired_resource' => 7], $now, $now);
    self::assertSame(['retired_resource' => 7], $plan->limits());
    self::assertSame(7, $plan->limitFor('retired_resource'));
    self::assertSame([], new OrganizationQuotaRegistry([])->definitions());
  }

  public function testDuplicateQuotaOwnershipIsRejected(): void
  {
    $this->expectException(LogicException::class);
    new OrganizationQuotaRegistry([$this->quotaContribution(), $this->quotaContribution()]);
  }

  public function testFakePermissionsArePublishedAndUnknownNewGrantsDenied(): void
  {
    $catalog = new OrganizationPermissionRegistry([$this->permissionContribution()]);
    self::assertSame('Read widgets', $catalog->descriptionFor('organization.widgets.read'));
    self::assertSame('', $catalog->descriptionFor('organization.retired.read'));
    $catalog->assertKnown(['organization.widgets.read']);
    $this->expectException(InvalidValueException::class);
    $catalog->assertKnown(['organization.retired.read']);
  }

  public function testDuplicatePermissionOwnershipIsRejected(): void
  {
    $this->expectException(LogicException::class);
    new OrganizationPermissionRegistry([$this->permissionContribution(), $this->permissionContribution()]);
  }

  public function testFakeSystemRoleGrantsMergeWithoutChangingHistoricalOrCustomRoles(): void
  {
    $catalog = new OrganizationSystemRoleRegistry([$this->roleContribution()]);
    self::assertSame(['organization.widgets.read'], $catalog->permissionsFor('member'));
    self::assertSame(['retired.permission', 'organization.widgets.read'], $catalog->mergePermissions('member', ['retired.permission'], true));
    self::assertSame(['retired.permission'], $catalog->mergePermissions('member', ['retired.permission'], false));
    self::assertSame(['retired.permission'], $catalog->mergePermissions('retired_role', ['retired.permission'], true));
  }

  public function testDuplicateSystemRoleContributionIsRejected(): void
  {
    $this->expectException(LogicException::class);
    new OrganizationSystemRoleRegistry([$this->roleContribution(), $this->roleContribution()]);
  }

  public function testFakeTypedPolicyDefaultsMergeAndHistoricalSectionsSurviveRemoval(): void
  {
    $catalog = new OrganizationSettingsPolicyRegistry([$this->settingsContribution()]);
    self::assertSame(['enabled' => false, 'retention_days' => 10], $catalog->defaults('widgets'));
    $settings = OrganizationSettings::fromArray(['retired_policy' => ['old_key' => true]], $catalog->allDefaults());
    self::assertSame(['enabled' => false, 'retention_days' => 10], $settings->additionalSections['widgets']);
    $merged = $catalog->merge('widgets', $settings->additionalSections['widgets'], ['enabled' => true, 'retention_days' => null]);
    $settings = $settings->withPolicySection('widgets', $merged);
    self::assertSame(['enabled' => true, 'retention_days' => 10], $settings->additionalSections['widgets']);
    $hydrated = OrganizationSettings::fromArray($settings->toArray());
    self::assertSame($settings->additionalSections, $hydrated->additionalSections);
    self::assertSame(['old_key' => true], $hydrated->additionalSections['retired_policy']);
  }

  public function testFakePolicyRejectsWrongStructuralType(): void
  {
    $this->expectException(InvalidArgumentException::class);
    new OrganizationSettingsPolicyRegistry([$this->settingsContribution()])->validatePartial('widgets', ['enabled' => 'true']);
  }

  public function testFakePolicyRejectsUnknownFieldsAndOutOfRangeValues(): void
  {
    $catalog = new OrganizationSettingsPolicyRegistry([$this->settingsContribution()]);

    try {
      $catalog->validatePartial('widgets', ['unknown' => true]);
      self::fail('Unknown policy field was accepted.');
    } catch (InvalidArgumentException $exception) {
      self::assertSame('Unknown organization settings field "widgets.unknown".', $exception->getMessage());
    }
    $this->expectException(InvalidArgumentException::class);
    $catalog->validatePartial('widgets', ['retention_days' => 366]);
  }

  public function testDuplicateSettingsFieldOwnershipIsRejected(): void
  {
    $this->expectException(LogicException::class);
    new OrganizationSettingsPolicyRegistry([$this->settingsContribution(), $this->settingsContribution()]);
  }

  public function testFakePolicySectionIsPersistedThroughTheExistingSettingsUseCase(): void
  {
    $catalog = new OrganizationSettingsPolicyRegistry([$this->settingsContribution()]);
    $organization = Organization::create(OrganizationId::fromString(self::ORGANIZATION_ID), new OrganizationName('Test organization'), 'owner');
    $repository = $this->createMock(OrganizationRepositoryPort::class);
    $repository->method('findById')->willReturn($organization);
    $repository->expects(self::once())->method('save')->with($organization);
    $transactions = $this->createStub(TransactionManagerPort::class);
    $transactions->method('transactional')->willReturnCallback(static fn (callable $work): mixed => $work());
    $events = $this->createMock(EventDispatcherPort::class);
    $events->expects(self::once())->method('dispatch')->with(self::callback(static fn (object $event): bool => $event instanceof OrganizationSettingsUpdatedEvent && ['widgets'] === $event->changedFields));
    $handler = new UpdateOrganizationSettingsHandler($repository, $transactions, $events, $this->createStub(EquipmentTypeCatalogPort::class), $catalog);
    $handler(new UpdateOrganizationSettingsCommand(self::ORGANIZATION_ID, policySections: ['widgets' => ['enabled' => true, 'retention_days' => 2]]));
    self::assertSame(['enabled' => true, 'retention_days' => 2], $organization->settings()->additionalSections['widgets']);
  }

  public function testUnknownEmptyPolicySectionFailsBeforeReadingOrSavingOrganization(): void
  {
    $repository = $this->createMock(OrganizationRepositoryPort::class);
    $repository->expects(self::never())->method('findById');
    $repository->expects(self::never())->method('save');
    $handler = new UpdateOrganizationSettingsHandler($repository, $this->createStub(TransactionManagerPort::class), $this->createStub(EventDispatcherPort::class), $this->createStub(EquipmentTypeCatalogPort::class), new OrganizationSettingsPolicyRegistry([$this->settingsContribution()]));
    $this->expectException(InvalidArgumentException::class);
    $handler(new UpdateOrganizationSettingsCommand(self::ORGANIZATION_ID, policySections: ['retired' => []]));
  }

  private function quotaContribution(): OrganizationQuotaContributionPort
  {
    return new class () implements OrganizationQuotaContributionPort {
      public static function definitions(): array
      {
        return [new OrganizationQuotaDefinition('widgets', 'Widgets', 'widgets', 'Widget capacity', 5)];
      }

      public function usage(string $organizationId, string $resourceKey): int
      {
        return 2;
      }
    };
  }

  private function permissionContribution(): OrganizationPermissionContributionPort
  {
    return new class () implements OrganizationPermissionContributionPort {
      public static function definitions(): array
      {
        return [new OrganizationPermissionDefinition('organization.widgets.read', 'Read widgets')];
      }
    };
  }

  private function roleContribution(): OrganizationSystemRoleContributionPort
  {
    return new class () implements OrganizationSystemRoleContributionPort {
      public static function definitions(): array
      {
        return [new OrganizationSystemRoleDefinition('widgets.member', 'member', ['organization.widgets.read'])];
      }
    };
  }

  private function settingsContribution(): OrganizationSettingsPolicyContributionPort
  {
    return new class () implements OrganizationSettingsPolicyContributionPort {
      public static function definitions(): array
      {
        return [
          new OrganizationSettingsPolicyDefinition('widgets.enabled', OrganizationSettingsValueType::BOOLEAN, false),
          new OrganizationSettingsPolicyDefinition('widgets.retention_days', OrganizationSettingsValueType::INTEGER, 10, 1, 365),
        ];
      }
    };
  }
}
