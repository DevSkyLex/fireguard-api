<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Presentation\Api\Processor\Attachment;

use ApiPlatform\Metadata\Post;
use Auth\Infrastructure\Security\User\SecurityUser;
use Facility\Application\Contract\Attachment\FacilityAttachmentView;
use Facility\Application\UseCase\Command\Attachment\SetPrimaryFacilityAttachment\SetPrimaryFacilityAttachmentCommand;
use Facility\Application\UseCase\Query\Attachment\GetFacilityMediaContext\{GetFacilityMediaContextQuery, GetFacilityMediaContextResult};
use Facility\Domain\Exception\{FacilityAccessDeniedException, FacilityAttachmentNotFloorPlanException, FacilityAttachmentNotFoundException};
use Facility\Presentation\Api\Processor\Attachment\SetPrimaryFacilityAttachmentProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Message\ResultMessage;
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, ConflictHttpException, NotFoundHttpException};
use Throwable;

/** Pins authorized primary-plan mutation and its persisted output query. */
#[CoversClass(SetPrimaryFacilityAttachmentProcessor::class)]
final class SetPrimaryFacilityAttachmentProcessorTest extends TestCase
{
  #[Test]
  public function promotionDispatchesOnceAndReadsTheUpdatedSnapshot(): void
  {
    $queries = $this->createMock(QueryBusPort::class);
    $queries->expects(self::exactly(2))->method('ask')->with(self::callback(static fn (object $query): bool => $query instanceof GetFacilityMediaContextQuery && $query->write && 'user-id' === $query->userId && 'attachment-id' === $query->attachmentId))->willReturn(new GetFacilityMediaContextResult('organization-id', 'facility-id'), new GetFacilityMediaContextResult('organization-id', 'facility-id', new FacilityAttachmentView('attachment-id', 'facility-id', 'plan.png', 'image/png', 2048, null, 'now', 'floor_plan', true, 800, 600, null, null, null, 8)));
    $commands = $this->createMock(CommandBusPort::class);
    $commands->expects(self::once())->method('dispatch')->with(self::callback(static fn (object $command): bool => $command instanceof SetPrimaryFacilityAttachmentCommand && 'organization-id' === $command->organizationId && 'facility-id' === $command->facilityId && 'attachment-id' === $command->attachmentId && 'user-id' === $command->userId))->willReturn($this->createStub(ResultMessage::class));
    $output = new SetPrimaryFacilityAttachmentProcessor($commands, $this->security(), $queries)->process(null, new Post(), ['id' => 'attachment-id']);
    self::assertTrue($output->isPrimaryPlan);
    self::assertSame(8, $output->revision);
  }

  /**
   * @param class-string<Throwable> $expected
   */
  #[Test]
  #[DataProvider('commandFailures')]
  public function commandRefusalsKeepTheirHttpStatus(Throwable $failure, string $expected): void
  {
    $queries = $this->createMock(QueryBusPort::class);
    $queries->expects(self::once())->method('ask')->willReturn(new GetFacilityMediaContextResult('organization-id', 'facility-id'));
    $commands = $this->createStub(CommandBusPort::class);
    $commands->method('dispatch')->willThrowException($failure);
    $this->expectException($expected);
    new SetPrimaryFacilityAttachmentProcessor($commands, $this->security(), $queries)->process(null, new Post(), ['id' => 'attachment-id']);
  }

  /**
   * @return list<array{Throwable,class-string<Throwable>}>
   */
  public static function commandFailures(): array
  {
    return [[FacilityAttachmentNotFloorPlanException::forAttachment('attachment-id'), ConflictHttpException::class], [FacilityAttachmentNotFoundException::withId('attachment-id'), NotFoundHttpException::class]];
  }

  #[Test]
  public function refusedScopeNeverDispatchesTheMutation(): void
  {
    $queries = $this->createStub(QueryBusPort::class);
    $queries->method('ask')->willThrowException(new FacilityAccessDeniedException('Missing permission.'));
    $commands = $this->createMock(CommandBusPort::class);
    $commands->expects(self::never())->method('dispatch');
    $this->expectException(FacilityAccessDeniedException::class);
    new SetPrimaryFacilityAttachmentProcessor($commands, $this->security(), $queries)->process(null, new Post(), ['id' => 'attachment-id']);
  }

  #[Test]
  public function unauthenticatedCallerNeverReachesTheQuery(): void
  {
    $queries = $this->createMock(QueryBusPort::class);
    $queries->expects(self::never())->method('ask');
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);
    $this->expectException(AccessDeniedHttpException::class);
    new SetPrimaryFacilityAttachmentProcessor($this->createStub(CommandBusPort::class), $security, $queries)->process(null, new Post(), ['id' => 'attachment-id']);
  }

  #[Test]
  public function missingIdentifierNeverReachesTheApplication(): void
  {
    $queries = $this->createMock(QueryBusPort::class);
    $queries->expects(self::never())->method('ask');
    $this->expectException(NotFoundHttpException::class);
    new SetPrimaryFacilityAttachmentProcessor($this->createStub(CommandBusPort::class), $this->security(), $queries)->process(null, new Post());
  }

  private function security(): Security
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(new SecurityUser('user-id', 'user@example.com', 'password', ['ROLE_USER'], [], true));

    return $security;
  }
}
