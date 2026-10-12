<?php

declare(strict_types=1);

namespace Tests\Unit\Onboarding\Application\Service;

use LogicException;
use Onboarding\Application\Contract\Step\{OrganizationOnboardingStepContext, OrganizationOnboardingStepDefinition};
use Onboarding\Application\Port\Inbound\OrganizationOnboardingStepContributionPort;
use Onboarding\Application\Port\Outbound\Internal\OrganizationOnboardingSessionRepositoryPort;
use Onboarding\Application\Service\{ExecuteOnboardingStepPayload, OrganizationOnboardingFlowService, OrganizationOnboardingStepRegistry};
use Onboarding\Domain\Exception\{OnboardingStepNotExecutableException, UnsupportedOnboardingStepException};
use Onboarding\Domain\Model\OrganizationOnboardingSession\OrganizationOnboardingSession;
use Onboarding\Infrastructure\Persistence\Doctrine\Mapper\OrganizationOnboardingSessionMapper;
use Onboarding\Presentation\Api\Mapper\Onboarding\OrganizationOnboardingOutputAssembler;
use Organization\Application\Contract\Onboarding\OnboardingOrganization;
use Organization\Application\Port\Inbound\OrganizationOnboardingDirectoryPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\{EventDispatcherPort, TransactionManagerPort, UuidGeneratorPort};
use Tests\Helper\Onboarding\RegisteredOrganizationOnboardingSteps;

use function array_map;

#[CoversClass(OrganizationOnboardingStepRegistry::class)]
final class OrganizationOnboardingStepRegistryTest extends TestCase
{
  #[Test]
  public function duplicateKeysAreRejectedDuringRegistryConstruction(): void
  {
    $fake = new FakeOnboardingStepContribution();
    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('Duplicate onboarding step key "configure_fake_feature".');
    new OrganizationOnboardingStepRegistry([$fake, $fake]);
  }

  #[Test]
  public function unknownExecutionsAreDeniedBeforeSessionLookup(): void
  {
    $repository = $this->createMock(OrganizationOnboardingSessionRepositoryPort::class);
    $repository->expects(self::never())->method('findByUserId');
    $service = $this->service(new FakeOnboardingStepContribution(), $repository);
    $this->expectException(UnsupportedOnboardingStepException::class);
    $service->executeStep('actor', 'unregistered_action', new ExecuteOnboardingStepPayload());
  }

  #[Test]
  public function fakeOwnerEvaluatesAndExecutesWithoutEngineChanges(): void
  {
    $fake = new FakeOnboardingStepContribution();
    $state = $this->service($fake)->executeStep('actor', 'configure_fake_feature', new ExecuteOnboardingStepPayload());
    self::assertSame(1, $fake->evaluations);
    self::assertSame(1, $fake->executions);
    self::assertSame('completed', $state->state);
    self::assertSame(['configure_fake_feature'], $state->completedSteps);
    self::assertFalse($state->canRollback);
    $output = OrganizationOnboardingOutputAssembler::fromState($state);
    self::assertSame('configure_fake_feature', $output->steps[0]->key);
    self::assertSame('Configure fake feature', $output->steps[0]->label);
    self::assertSame('completed', $output->steps[0]->status);
  }

  #[Test]
  public function ownerPreconditionDenialCannotExecuteOrCompleteTheStep(): void
  {
    $fake = new FakeOnboardingStepContribution();
    $fake->ready = false;

    try {
      $this->service($fake)->executeStep('actor', 'configure_fake_feature', new ExecuteOnboardingStepPayload());
      self::fail('An unmet owner precondition must deny confirmation.');
    } catch (OnboardingStepNotExecutableException) {
      self::assertSame(1, $fake->evaluations);
      self::assertSame(0, $fake->executions);
    }
  }

  #[Test]
  public function historiesRoundTripAfterTheContributorIsRemoved(): void
  {
    $session = OrganizationOnboardingSession::start('session', 'actor');
    $session->markStepCompleted('retired_owner_step');
    $session->markStepSkipped('retired_optional_step');
    $hydrated = OrganizationOnboardingSessionMapper::toDomain(OrganizationOnboardingSessionMapper::toRecord($session));
    self::assertSame(['retired_owner_step'], $hydrated->completedSteps());
    self::assertSame(['retired_optional_step'], $hydrated->skippedSteps());
    self::assertSame('retired_owner_step', $hydrated->stepHistory()[0]->stepKey);
    $this->expectException(UnsupportedOnboardingStepException::class);
    new OrganizationOnboardingStepRegistry([])->get('retired_owner_step');
  }

  #[Test]
  public function registeredFireGuardMetadataPreservesWireOrderAndDefaults(): void
  {
    self::assertSame([
      'create_organization', 'select_plan', 'invite_members', 'create_first_facility', 'create_first_equipment',
    ], RegisteredOrganizationOnboardingSteps::keys());
    self::assertSame([true, false, false, true, true], array_map(
      static fn (OrganizationOnboardingStepDefinition $step): bool => $step->required,
      RegisteredOrganizationOnboardingSteps::definitions(),
    ));
  }

  private function service(
    FakeOnboardingStepContribution $fake,
    ?OrganizationOnboardingSessionRepositoryPort $repository = null,
  ): OrganizationOnboardingFlowService {
    $session = OrganizationOnboardingSession::start('session', 'actor', 'configure_fake_feature');
    $session->chooseCreation();
    if (null === $repository) {
      $repository = $this->createStub(OrganizationOnboardingSessionRepositoryPort::class);
      $repository->method('findByUserId')->willReturn($session);
    }
    $directory = $this->createStub(OrganizationOnboardingDirectoryPort::class);
    $directory->method('organizationsForUser')->willReturn([
      new OnboardingOrganization('org', 'Fake workspace', 'fake-workspace', 'actor', 'actor', true, $session->createdAt()->modify('+1 second')),
    ]);
    $transactions = $this->createStub(TransactionManagerPort::class);
    $transactions->method('transactional')->willReturnCallback(static fn (callable $work): mixed => $work());

    return new OrganizationOnboardingFlowService(
      sessionRepository: $repository,
      organizationDirectory: $directory,
      steps: new OrganizationOnboardingStepRegistry([$fake]),
      uuidFactory: $this->createStub(UuidGeneratorPort::class),
      transactionManager: $transactions,
      eventDispatcher: $this->createStub(EventDispatcherPort::class),
    );
  }
}

/**
 * Class FakeOnboardingStepContribution
 *
 * Proves that the registry and engine accept an independent owning module.
 *
 * @category TestDouble
 */
final class FakeOnboardingStepContribution implements OrganizationOnboardingStepContributionPort
{
  public bool $ready = true;

  public int $evaluations = 0;

  public int $executions = 0;

  public static function definition(): OrganizationOnboardingStepDefinition
  {
    return new OrganizationOnboardingStepDefinition('configure_fake_feature', 'Configure fake feature', 10, true);
  }

  public function evaluate(OrganizationOnboardingStepContext $context): bool
  {
    ++$this->evaluations;

    return $this->ready && null !== $context->organizationId;
  }

  public function execute(OrganizationOnboardingStepContext $context): void
  {
    ++$this->executions;
  }
}
