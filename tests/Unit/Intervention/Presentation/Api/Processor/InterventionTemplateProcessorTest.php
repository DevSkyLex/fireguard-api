<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Presentation\Api\Processor;

use ApiPlatform\Metadata\{Delete, Patch, Post};
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Intervention\Application\Contract\Template\InterventionTemplateView;
use Intervention\Application\UseCase\Command\Template\CreateInterventionTemplate\{CreateInterventionTemplateCommand, CreateInterventionTemplateResult};
use Intervention\Application\UseCase\Command\Template\DeleteInterventionTemplate\DeleteInterventionTemplateCommand;
use Intervention\Application\UseCase\Command\Template\UpdateInterventionTemplate\{UpdateInterventionTemplateCommand, UpdateInterventionTemplateResult};
use Intervention\Domain\Exception\InterventionValidationException;
use Intervention\Presentation\Api\Dto\Input\{CreateInterventionTemplateInput, InterventionTemplateItemInput, UpdateInterventionTemplateInput};
use Intervention\Presentation\Api\Dto\Output\InterventionTemplateOutput;
use Intervention\Presentation\Api\Factory\InterventionTemplateOutputFactory;
use Intervention\Presentation\Api\Processor\InterventionTemplateProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Message\VoidResult;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Presentation\Api\Http\MergePatchFields;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, UnprocessableEntityHttpException};

/**
 * Test InterventionTemplateProcessorTest.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InterventionTemplateProcessor::class)]
final class InterventionTemplateProcessorTest extends TestCase
{
  private const string USER_ID = '550e8400-e29b-41d4-a716-446655441300';

  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string TEMPLATE_ID = '550e8400-e29b-41d4-a716-446655441700';

  private const string MEMBER_ID = '550e8400-e29b-41d4-a716-446655441501';

  private const string SITE_ID = '550e8400-e29b-41d4-a716-446655441502';

  // #region Methods
  #[Test]
  public function testProcessCreatesATemplateAndFlattensTheItemInputs(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static function (CreateInterventionTemplateCommand $command): bool {
        self::assertSame(self::USER_ID, $command->userId);
        self::assertSame(self::ORG_ID, $command->organizationId);
        self::assertSame(self::SITE_ID, $command->defaultSiteId);
        self::assertSame(self::MEMBER_ID, $command->defaultResponsibleId);
        self::assertSame([[
          'action' => 'inspection',
          'target' => null,
          'resultResource' => null,
          'required' => true,
          'defaultAssigneeId' => self::MEMBER_ID,
        ]], $command->items);

        return true;
      }))
      ->willReturn(new CreateInterventionTemplateResult($this->view()));

    $item = new InterventionTemplateItemInput();
    $item->action = 'inspection';
    $item->defaultAssignee = $this->memberIri();

    $input = new CreateInterventionTemplateInput();
    $input->organization = '/api/organizations/' . self::ORG_ID;
    $input->name = 'Quarterly audit';
    $input->defaultSite = '/api/facilities/' . self::SITE_ID;
    $input->defaultResponsible = $this->memberIri();
    $input->items = [$item];

    $output = $this->processor($commandBus, $this->mergePatchFields())->process($input, new Post());

    self::assertInstanceOf(InterventionTemplateOutput::class, $output);
    self::assertSame(self::TEMPLATE_ID, $output->id);
  }

  #[Test]
  public function testProcessAppliesOnlyTheFieldsPresentInTheMergePatchBody(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static function (UpdateInterventionTemplateCommand $command): bool {
        self::assertSame(self::TEMPLATE_ID, $command->templateId);
        self::assertTrue($command->hasName);
        self::assertFalse($command->hasItems);
        self::assertFalse($command->hasLabelIds);
        self::assertNull($command->items);
        self::assertNull($command->defaultSiteId);

        return true;
      }))
      ->willReturn(new UpdateInterventionTemplateResult($this->view()));

    $input = new UpdateInterventionTemplateInput();
    $input->name = 'Renamed';

    $output = $this->processor($commandBus, $this->mergePatchFields('{"name":"Renamed"}'))
      ->process($input, new Patch(), ['id' => self::TEMPLATE_ID]);

    self::assertInstanceOf(InterventionTemplateOutput::class, $output);
  }

  #[Test]
  public function testProcessDeletesAndReturnsNull(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::isInstanceOf(DeleteInterventionTemplateCommand::class))
      ->willReturn(new VoidResult());

    $output = $this->processor($commandBus, $this->mergePatchFields())
      ->process(null, new Delete(), ['id' => self::TEMPLATE_ID]);

    self::assertNull($output);
  }

  #[Test]
  public function testProcessRequiresAnIdForPatch(): void
  {
    $processor = $this->processor($this->createStub(CommandBusPort::class), $this->mergePatchFields());

    $this->expectException(BadRequestHttpException::class);

    $processor->process(new UpdateInterventionTemplateInput(), new Patch(), []);
  }

  #[Test]
  public function testProcessRequiresAnIdForDelete(): void
  {
    $processor = $this->processor($this->createStub(CommandBusPort::class), $this->mergePatchFields());

    $this->expectException(BadRequestHttpException::class);

    $processor->process(null, new Delete(), []);
  }

  #[Test]
  public function testProcessRequiresAnAuthenticatedUser(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $processor = new InterventionTemplateProcessor(
      $this->createStub(CommandBusPort::class),
      new InterventionTemplateOutputFactory(),
      $security,
      $this->mergePatchFields(),
    );

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(null, new Delete(), ['id' => self::TEMPLATE_ID]);
  }

  #[Test]
  public function testProcessMapsADomainFailureToItsHttpEquivalent(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(new InterventionValidationException('Empty template.'));

    $input = new CreateInterventionTemplateInput();
    $input->organization = '/api/organizations/' . self::ORG_ID;
    $input->name = 'Quarterly audit';

    $this->expectException(UnprocessableEntityHttpException::class);

    $this->processor($commandBus, $this->mergePatchFields())->process($input, new Post());
  }

  private function processor(CommandBusPort $commandBus, MergePatchFields $mergePatchFields): InterventionTemplateProcessor
  {
    return new InterventionTemplateProcessor(
      $commandBus,
      new InterventionTemplateOutputFactory(),
      $this->security(),
      $mergePatchFields,
    );
  }

  private function memberIri(): string
  {
    return '/api/organizations/' . self::ORG_ID . '/members/' . self::MEMBER_ID;
  }

  private function mergePatchFields(?string $jsonBody = null): MergePatchFields
  {
    $requestStack = new RequestStack();
    if (null !== $jsonBody) {
      $requestStack->push(Request::create('/api/intervention-templates/' . self::TEMPLATE_ID, 'PATCH', content: $jsonBody));
    }

    return new MergePatchFields($requestStack);
  }

  private function security(): Security
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

    return $security;
  }

  private function view(): InterventionTemplateView
  {
    return new InterventionTemplateView(
      self::TEMPLATE_ID,
      self::ORG_ID,
      'Quarterly audit',
      null,
      'site_setup',
      'normal',
      null,
      null,
      null,
      [],
      [],
      new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
    );
  }
  // #endregion
}
