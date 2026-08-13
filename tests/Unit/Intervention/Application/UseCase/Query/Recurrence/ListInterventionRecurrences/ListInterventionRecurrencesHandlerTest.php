<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Application\UseCase\Query\Recurrence\ListInterventionRecurrences;

use Intervention\Application\Contract\Recurrence\InterventionRecurrencePage;
use Intervention\Application\Port\Outbound\InterventionRecurrencePort;
use Intervention\Application\UseCase\Query\Recurrence\ListInterventionRecurrences\{
  ListInterventionRecurrencesHandler,
  ListInterventionRecurrencesQuery
};
use Intervention\Domain\Exception\{InterventionAccessDeniedException, InterventionNotFoundException};
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function sprintf;

/**
 * Test ListInterventionRecurrencesHandlerTest.
 *
 * @category Test
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ListInterventionRecurrencesHandlerTest extends TestCase
{
  private const string USER_ID = '11111111-1111-4111-8111-111111111111';

  private const string ORGANIZATION_ID = '22222222-2222-4222-8222-222222222222';

  private const string PERMISSION = 'organization.interventions.read';

  #[Test]
  public function itReturnsTheRecurrencesPageWhenPermissionIsGranted(): void
  {
    $page = new InterventionRecurrencePage([], 2, 15, 0);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);

    $recurrences = $this->createMock(InterventionRecurrencePort::class);
    $recurrences->expects(self::once())
      ->method('list')
      ->with(self::ORGANIZATION_ID, 2, 15, true)
      ->willReturn($page);

    $handler = new ListInterventionRecurrencesHandler($recurrences, $authorization);

    $result = $handler(new ListInterventionRecurrencesQuery(
      self::USER_ID,
      self::ORGANIZATION_ID,
      2,
      15,
      true,
    ));

    self::assertSame($page, $result->page);
  }

  #[Test]
  public function itForwardsTheDefaultPaginationAndNullActiveFilter(): void
  {
    $page = new InterventionRecurrencePage([], 1, 30, 0);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);

    $recurrences = $this->createMock(InterventionRecurrencePort::class);
    $recurrences->expects(self::once())
      ->method('list')
      ->with(self::ORGANIZATION_ID, 1, 30, null)
      ->willReturn($page);

    $handler = new ListInterventionRecurrencesHandler($recurrences, $authorization);

    $result = $handler(new ListInterventionRecurrencesQuery(
      self::USER_ID,
      self::ORGANIZATION_ID,
    ));

    self::assertSame($page, $result->page);
  }

  #[Test]
  public function itThrowsWhenTheReadPermissionIsMissing(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::MISSING_PERMISSION);

    $recurrences = $this->createMock(InterventionRecurrencePort::class);
    $recurrences->expects(self::never())->method('list');

    $handler = new ListInterventionRecurrencesHandler($recurrences, $authorization);

    $this->expectException(InterventionAccessDeniedException::class);
    $this->expectExceptionMessage('Missing ' . self::PERMISSION . ' permission.');

    $handler(new ListInterventionRecurrencesQuery(
      self::USER_ID,
      self::ORGANIZATION_ID,
    ));
  }

  #[Test]
  public function testInvokeThrowsNotFoundWhenTheCallerIsOutsideTheOwningOrganization(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::OUTSIDE_SCOPE);

    $recurrences = $this->createMock(InterventionRecurrencePort::class);
    $recurrences->expects(self::never())->method('list');

    $handler = new ListInterventionRecurrencesHandler($recurrences, $authorization);

    $this->expectException(InterventionNotFoundException::class);
    $this->expectExceptionMessage(sprintf('Organization with ID "%s" not found.', self::ORGANIZATION_ID));

    $handler(new ListInterventionRecurrencesQuery(
      self::USER_ID,
      self::ORGANIZATION_ID,
    ));
  }
}
