<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Application\UseCase\Query\Label\ListInterventionLabels;

use DateTimeImmutable;
use Intervention\Application\Contract\Label\{InterventionLabelPage, InterventionLabelView};
use Intervention\Application\Port\Outbound\InterventionLabelPort;
use Intervention\Application\UseCase\Query\Label\ListInterventionLabels\{
  ListInterventionLabelsHandler,
  ListInterventionLabelsQuery
};
use Intervention\Domain\Exception\{InterventionAccessDeniedException, InterventionNotFoundException};
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function sprintf;

/**
 * Test ListInterventionLabelsHandlerTest.
 *
 * @category Test
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ListInterventionLabelsHandlerTest extends TestCase
{
  private const string USER_ID = '11111111-1111-4111-8111-111111111111';

  private const string ORGANIZATION_ID = '22222222-2222-4222-8222-222222222222';

  #[Test]
  public function itListsLabelsWhenPermissionIsGranted(): void
  {
    $view = new InterventionLabelView(
      id: '33333333-3333-4333-8333-333333333333',
      organizationId: self::ORGANIZATION_ID,
      name: 'Urgent',
      color: '#ff0000',
      createdAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-01-02T00:00:00+00:00'),
    );
    $page = new InterventionLabelPage([$view], 2, 20, 1);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);

    $labels = $this->createMock(InterventionLabelPort::class);
    $labels->expects(self::once())
      ->method('list')
      ->with(self::ORGANIZATION_ID, 2, 20)
      ->willReturn($page);

    $handler = new ListInterventionLabelsHandler($labels, $authorization);

    $result = $handler(new ListInterventionLabelsQuery(self::USER_ID, self::ORGANIZATION_ID, 2, 20));

    self::assertSame($page, $result->page);
  }

  #[Test]
  public function itThrowsWhenTheReadPermissionIsMissing(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::MISSING_PERMISSION);

    $labels = $this->createMock(InterventionLabelPort::class);
    $labels->expects(self::never())->method('list');

    $handler = new ListInterventionLabelsHandler($labels, $authorization);

    $this->expectException(InterventionAccessDeniedException::class);
    $this->expectExceptionMessage('Missing organization.interventions.read permission.');

    $handler(new ListInterventionLabelsQuery(self::USER_ID, self::ORGANIZATION_ID, 1, 10));
  }

  #[Test]
  public function testInvokeThrowsNotFoundWhenTheCallerIsOutsideTheOwningOrganization(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::OUTSIDE_SCOPE);

    $labels = $this->createMock(InterventionLabelPort::class);
    $labels->expects(self::never())->method('list');

    $handler = new ListInterventionLabelsHandler($labels, $authorization);

    $this->expectException(InterventionNotFoundException::class);
    $this->expectExceptionMessage(sprintf('Organization with ID "%s" not found.', self::ORGANIZATION_ID));

    $handler(new ListInterventionLabelsQuery(self::USER_ID, self::ORGANIZATION_ID, 1, 10));
  }
}
