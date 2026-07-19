<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Application\UseCase\Command\Template\CreateInterventionTemplate;

use DateTimeImmutable;
use Intervention\Application\Contract\Template\InterventionTemplateView;
use Intervention\Application\Port\Outbound\InterventionTemplatePort;
use Intervention\Application\UseCase\Command\Template\CreateInterventionTemplate\{CreateInterventionTemplateCommand, CreateInterventionTemplateHandler};
use Intervention\Domain\Exception\{InterventionAccessDeniedException, InterventionValidationException};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test CreateInterventionTemplateHandlerTest.
 *
 * @category UseCase Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(CreateInterventionTemplateHandler::class)]
final class CreateInterventionTemplateHandlerTest extends TestCase
{
  private const string ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63c11';

  private const string USER_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63c14';

  #[Test]
  public function itCreatesATrimmedTemplateWhenTheUserHasThePermission(): void
  {
    $view = self::view();
    $templates = $this->createMock(InterventionTemplatePort::class);
    $templates->expects(self::once())
      ->method('create')
      ->with(
        self::ORGANIZATION_ID,
        'Fire safety audit',
        'A description',
        'inspection_campaign',
        'high',
        null,
        null,
        'P14D',
        ['label-1'],
        [['action' => 'inspection', 'target' => null, 'resultResource' => null, 'required' => true, 'defaultAssigneeId' => null]],
      )
      ->willReturn($view);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $result = new CreateInterventionTemplateHandler($templates, $authorization)(
      new CreateInterventionTemplateCommand(
        userId: self::USER_ID,
        organizationId: self::ORGANIZATION_ID,
        name: '  Fire safety audit  ',
        description: 'A description',
        type: 'inspection_campaign',
        priority: 'high',
        defaultSiteId: null,
        defaultResponsibleId: null,
        duration: 'P14D',
        labelIds: ['label-1'],
        items: [['action' => 'inspection', 'target' => null, 'resultResource' => null, 'required' => true, 'defaultAssigneeId' => null]],
      ),
    );

    self::assertSame($view, $result->template);
  }

  #[Test]
  public function itRejectsAUserMissingThePlanPermission(): void
  {
    $templates = $this->createMock(InterventionTemplatePort::class);
    $templates->expects(self::never())->method('create');
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(false);

    $this->expectException(InterventionAccessDeniedException::class);

    new CreateInterventionTemplateHandler($templates, $authorization)(self::command());
  }

  #[Test]
  public function itRejectsABlankName(): void
  {
    $templates = $this->createMock(InterventionTemplatePort::class);
    $templates->expects(self::never())->method('create');
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $this->expectException(InterventionValidationException::class);

    new CreateInterventionTemplateHandler($templates, $authorization)(
      self::command(name: '   '),
    );
  }

  #[Test]
  public function itRejectsAnInvalidType(): void
  {
    $templates = $this->createMock(InterventionTemplatePort::class);
    $templates->expects(self::never())->method('create');
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $this->expectException(InterventionValidationException::class);

    new CreateInterventionTemplateHandler($templates, $authorization)(
      self::command(type: 'not_a_type'),
    );
  }

  #[Test]
  public function itRejectsAnInvalidPriority(): void
  {
    $templates = $this->createMock(InterventionTemplatePort::class);
    $templates->expects(self::never())->method('create');
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $this->expectException(InterventionValidationException::class);

    new CreateInterventionTemplateHandler($templates, $authorization)(
      self::command(priority: 'not_a_priority'),
    );
  }

  #[Test]
  public function itRejectsAnUnparseableDuration(): void
  {
    $templates = $this->createMock(InterventionTemplatePort::class);
    $templates->expects(self::never())->method('create');
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $this->expectException(InterventionValidationException::class);

    new CreateInterventionTemplateHandler($templates, $authorization)(
      self::command(duration: 'not-a-duration'),
    );
  }

  #[Test]
  public function itRejectsABlankItemAction(): void
  {
    $templates = $this->createMock(InterventionTemplatePort::class);
    $templates->expects(self::never())->method('create');
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $this->expectException(InterventionValidationException::class);

    new CreateInterventionTemplateHandler($templates, $authorization)(
      self::command(items: [['action' => '   ', 'target' => null, 'resultResource' => null, 'required' => true, 'defaultAssigneeId' => null]]),
    );
  }

  /**
   * @param list<array{action: string, target: ?string, resultResource: ?string, required: bool, defaultAssigneeId: ?string}> $items
   */
  private static function command(
    string $name = 'Fire safety audit',
    string $type = 'inspection_campaign',
    string $priority = 'high',
    ?string $duration = null,
    array $items = [],
  ): CreateInterventionTemplateCommand {
    return new CreateInterventionTemplateCommand(
      userId: self::USER_ID,
      organizationId: self::ORGANIZATION_ID,
      name: $name,
      description: null,
      type: $type,
      priority: $priority,
      defaultSiteId: null,
      defaultResponsibleId: null,
      duration: $duration,
      labelIds: [],
      items: $items,
    );
  }

  private static function view(): InterventionTemplateView
  {
    return new InterventionTemplateView(
      'template-1',
      self::ORGANIZATION_ID,
      'Fire safety audit',
      'A description',
      'inspection_campaign',
      'high',
      null,
      null,
      'P14D',
      ['label-1'],
      [],
      new DateTimeImmutable(),
      new DateTimeImmutable(),
    );
  }
}
