<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Application\UseCase\Query\Recurrence\GetInterventionRecurrence;

use DateTimeImmutable;
use Intervention\Application\Contract\Recurrence\InterventionRecurrenceView;
use Intervention\Application\Port\Outbound\InterventionRecurrencePort;
use Intervention\Application\UseCase\Query\Recurrence\GetInterventionRecurrence\{
  GetInterventionRecurrenceHandler,
  GetInterventionRecurrenceQuery
};
use Intervention\Domain\Exception\{InterventionAccessDeniedException, InterventionNotFoundException};
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function sprintf;

/**
 * Test GetInterventionRecurrenceHandlerTest.
 *
 * @category Test
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class GetInterventionRecurrenceHandlerTest extends TestCase
{
  private const string USER_ID = '11111111-1111-4111-8111-111111111111';

  private const string ORGANIZATION_ID = '22222222-2222-4222-8222-222222222222';

  private const string RECURRENCE_ID = '33333333-3333-4333-8333-333333333333';

  #[Test]
  public function itReturnsTheRecurrenceWhenPermissionIsGranted(): void
  {
    $view = $this->recurrenceView();

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);

    $recurrences = $this->createMock(InterventionRecurrencePort::class);
    $recurrences->expects(self::once())
      ->method('find')
      ->with(self::RECURRENCE_ID)
      ->willReturn($view);

    $handler = new GetInterventionRecurrenceHandler($recurrences, $authorization);

    $result = $handler(new GetInterventionRecurrenceQuery(self::USER_ID, self::RECURRENCE_ID));

    self::assertSame($view, $result->recurrence);
  }

  #[Test]
  public function itThrowsWhenTheRecurrenceIsNotFound(): void
  {
    $recurrences = $this->createStub(InterventionRecurrencePort::class);
    $recurrences->method('find')->willReturn(null);

    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::never())->method('resolveAccess');

    $handler = new GetInterventionRecurrenceHandler($recurrences, $authorization);

    $this->expectException(InterventionNotFoundException::class);
    $this->expectExceptionMessage(sprintf('Intervention with ID "%s" not found.', self::RECURRENCE_ID));

    $handler(new GetInterventionRecurrenceQuery(self::USER_ID, self::RECURRENCE_ID));
  }

  #[Test]
  public function itThrowsWhenTheReadPermissionIsMissing(): void
  {
    $recurrences = $this->createStub(InterventionRecurrencePort::class);
    $recurrences->method('find')->willReturn($this->recurrenceView());

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::MISSING_PERMISSION);

    $handler = new GetInterventionRecurrenceHandler($recurrences, $authorization);

    $this->expectException(InterventionAccessDeniedException::class);
    $this->expectExceptionMessage('Missing organization.interventions.read permission.');

    $handler(new GetInterventionRecurrenceQuery(self::USER_ID, self::RECURRENCE_ID));
  }

  #[Test]
  public function testInvokeThrowsNotFoundWhenTheCallerIsOutsideTheOwningOrganization(): void
  {
    $recurrences = $this->createStub(InterventionRecurrencePort::class);
    $recurrences->method('find')->willReturn($this->recurrenceView());

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::OUTSIDE_SCOPE);

    $handler = new GetInterventionRecurrenceHandler($recurrences, $authorization);

    $this->expectException(InterventionNotFoundException::class);
    $this->expectExceptionMessage(sprintf('Intervention with ID "%s" not found.', self::RECURRENCE_ID));

    $handler(new GetInterventionRecurrenceQuery(self::USER_ID, self::RECURRENCE_ID));
  }

  private function recurrenceView(): InterventionRecurrenceView
  {
    return new InterventionRecurrenceView(
      id: self::RECURRENCE_ID,
      organizationId: self::ORGANIZATION_ID,
      templateId: '44444444-4444-4444-8444-444444444444',
      name: 'Quarterly extinguisher check',
      siteId: null,
      responsibleId: null,
      frequency: 'quarterly',
      interval: 1,
      anchorDate: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      timezone: 'Europe/Paris',
      leadTimeDays: 7,
      nextOccurrenceAt: new DateTimeImmutable('2026-04-01T00:00:00+00:00'),
      lastMaterializedAt: null,
      isActive: true,
      endAt: null,
      createdAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-01-02T00:00:00+00:00'),
    );
  }
}
