<?php

declare(strict_types=1);

namespace Tests\Unit\Approval\Presentation\Api\Processor;

use ApiPlatform\Metadata\Post;
use Approval\Application\UseCase\Command\Decision\WithdrawApprovalRequest\{WithdrawApprovalRequestCommand, WithdrawApprovalRequestResult};
use Approval\Domain\Model\ApprovalRequest\ApprovalRequest;
use Approval\Domain\ValueObject\ApprovalRequestId;
use Approval\Presentation\Api\Dto\Input\WithdrawApprovalRequestInput;
use Approval\Presentation\Api\Factory\ApprovalRequestOutputFactory;
use Approval\Presentation\Api\Processor\WithdrawApprovalRequestProcessor;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Application\Port\Outbound\CurrentActorPort;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class WithdrawApprovalRequestProcessorTest extends TestCase
{
  public function testTranslatesActorUriAndNoteWithoutChangingBusinessMeaning(): void
  {
    $id = '018f0b68-6758-7a12-8a1d-3f0d97f64d01';
    $now = new DateTimeImmutable();
    $request = ApprovalRequest::create(ApprovalRequestId::fromString($id), 'org', 'nc_waiver', 'subject', 'member', 'user', [], $now->modify('+1 day'), $now);
    $request->withdraw('member', 'user', 'Keep the record', $now);
    $bus = $this->createMock(CommandBusPort::class);
    $bus->expects(self::once())->method('dispatch')->with(self::callback(static fn (WithdrawApprovalRequestCommand $command): bool => 'user' === $command->actorUserId && 'org' === $command->organizationId && $id === $command->requestId && 'Keep the record' === $command->decisionNote))->willReturn(WithdrawApprovalRequestResult::fromDomain($request));
    $actor = $this->createStub(CurrentActorPort::class);
    $actor->method('userId')->willReturn('user');
    $input = new WithdrawApprovalRequestInput();
    $input->decisionNote = 'Keep the record';
    $output = new WithdrawApprovalRequestProcessor($bus, $actor, new ApprovalRequestOutputFactory())->process($input, new Post(), ['organizationId' => 'org', 'requestId' => $id]);
    self::assertSame('withdrawn', $output->status);
    self::assertSame('Keep the record', $output->decisionNote);
  }

  public function testMissingActorNeverDispatches(): void
  {
    $bus = $this->createMock(CommandBusPort::class);
    $bus->expects(self::never())->method('dispatch');
    $actor = $this->createStub(CurrentActorPort::class);
    $actor->method('userId')->willReturn(null);
    $this->expectException(AccessDeniedHttpException::class);
    new WithdrawApprovalRequestProcessor($bus, $actor, new ApprovalRequestOutputFactory())->process(new WithdrawApprovalRequestInput(), new Post());
  }
}
