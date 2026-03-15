<?php

declare(strict_types=1);

namespace Tests\Unit\Onboarding\Domain\Model\OrganizationOnboardingSession\RollbackAction;

use LogicException;
use Onboarding\Domain\Model\OrganizationOnboardingSession\RollbackAction\{
  DeleteOrganizationRollbackAction,
  RollbackActionFactory
};
use Onboarding\Domain\ValueObject\OrganizationOnboardingStep;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

#[CoversClass(RollbackActionFactory::class)]
#[CoversClass(DeleteOrganizationRollbackAction::class)]
final class RollbackActionFactoryTest extends TestCase
{
  #[Test]
  public function testFromArrayCreatesDeleteOrganizationAction(): void
  {
    $data = [
      'step' => OrganizationOnboardingStep::CREATE_ORGANIZATION,
      'action' => DeleteOrganizationRollbackAction::ACTION_TYPE,
      'organizationId' => 'org-abc-123',
    ];

    $action = RollbackActionFactory::fromArray($data);

    self::assertInstanceOf(DeleteOrganizationRollbackAction::class, $action);
    self::assertSame('org-abc-123', $action->organizationId);
    self::assertSame(OrganizationOnboardingStep::CREATE_ORGANIZATION, $action->step());
    self::assertSame(DeleteOrganizationRollbackAction::ACTION_TYPE, $action->actionType());
  }

  #[Test]
  public function testFromArrayThrowsForUnknownActionType(): void
  {
    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('Unsupported rollback action type "unknown_action".');

    RollbackActionFactory::fromArray([
      'step' => 'some_step',
      'action' => 'unknown_action',
    ]);
  }

  #[Test]
  public function testFromArrayThrowsWhenActionDiscriminatorMissing(): void
  {
    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('"action" discriminator');

    RollbackActionFactory::fromArray(['step' => 'some_step']);
  }

  #[Test]
  public function testFromArrayThrowsWhenActionDiscriminatorIsEmpty(): void
  {
    $this->expectException(LogicException::class);

    RollbackActionFactory::fromArray(['action' => '']);
  }

  #[Test]
  public function testDeleteOrganizationRollbackActionRoundTrip(): void
  {
    $original = new DeleteOrganizationRollbackAction('org-round-trip-456');

    $array = $original->toArray();
    $restored = RollbackActionFactory::fromArray($array);

    self::assertInstanceOf(DeleteOrganizationRollbackAction::class, $restored);
    self::assertSame($original->organizationId, $restored->organizationId);
    self::assertSame($original->step(), $restored->step());
    self::assertSame($original->actionType(), $restored->actionType());
  }
}
