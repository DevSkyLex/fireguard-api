<?php

declare(strict_types=1);

namespace Tests\Unit\Automation\Infrastructure\Adapter\Idempotency;

use Automation\Application\Contract\Policy\AutomationPolicy;
use Automation\Application\Contract\Run\{AutomationAttemptView, AutomationRetry};
use Automation\Application\Port\Outbound\{AutomationPolicyPort, AutomationRuleQueuePort, AutomationRunHistoryPort};
use Automation\Application\UseCase\Command\RetryAutomationAttempt\{RetryAutomationAttemptCommand, RetryAutomationAttemptHandler, RetryAutomationAttemptResult};
use Automation\Domain\Exception\{AutomationRetryNotAllowedException, AutomationRunNotFoundException};
use Automation\Infrastructure\Adapter\Idempotency\{AutomationHttpIdempotencyOperationContributionAdapter, AutomationHttpIdempotencyReplayAuthorizationAdapter};
use Doctrine\DBAL\Connection;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Idempotency\IdempotencyDeniedException;
use Shared\Application\Port\Outbound\TransactionManagerPort;
use Shared\Infrastructure\Idempotency\HttpIdempotencyResultCodec;
use Tests\Support\Idempotency\OwnerReplayTestSupport;
use Throwable;

/** @category Unit Test */
final class AutomationHttpIdempotencyReplayAuthorizationAdapterTest extends TestCase
{
  use OwnerReplayTestSupport;

  /**
   * @param class-string<Throwable> $exception
   */
  #[DataProvider('changes')]
  public function testRetryReceiptCannotBypassChangedGrantsPolicyOrRunScope(string $change, string $exception): void
  {
    $permissions = ['organization.automation.read', 'organization.automation.manage'];
    [$ordinary, $organizations, $evidence] = $this->authorities($permissions);
    $enabled = true;
    $organization = self::ORGANIZATION;
    $attemptRun = self::TARGET;
    $connection = $this->createStub(Connection::class);
    $connection->method('fetchAssociative')->willReturnCallback(static function (string $sql) use (&$organization, &$attemptRun): array {
      return ['organization_id' => $organization, 'rule_key' => 'auto_create_intervention_on_critical_nc', 'run_id' => $attemptRun];
    });
    $policy = $this->createStub(AutomationPolicyPort::class);
    $policy->method('policyFor')->willReturnCallback(static function () use (&$enabled): AutomationPolicy { return new AutomationPolicy($enabled, []); });
    $gate = new AutomationHttpIdempotencyReplayAuthorizationAdapter($ordinary, $organizations, $policy, $connection, $evidence);
    $request = $this->request($this->operation(AutomationHttpIdempotencyOperationContributionAdapter::definitions(), 'automation_retry_attempt'), ['organizationId' => self::ORGANIZATION, 'runId' => self::TARGET], $this->jsonBody(['attemptId' => self::CHILD]));
    $proof = $this->proof($request, $gate->authorize($request, null), self::TARGET, $permissions);
    self::assertSame(self::CHILD, $gate->authorize($request, $proof)['automationAttemptId']);
    match ($change) {
      'grant' => $permissions = ['organization.automation.read'],
      'organization' => $organization = self::RELATED,
      'attempt' => $attemptRun = self::RELATED,
      default => $enabled = false,
    };
    $this->expectException($exception);
    $gate->authorize($request, $proof);
  }

  /**
   * @return iterable<string,array{string,class-string<Throwable>}>
   */
  public static function changes(): iterable
  {
    yield 'revoked management grant' => ['grant', IdempotencyDeniedException::class];
    yield 'disabled policy' => ['policy', AutomationRetryNotAllowedException::class];
    yield 'foreign run' => ['organization', AutomationRunNotFoundException::class];
    yield 'foreign attempt' => ['attempt', AutomationRunNotFoundException::class];
  }

  public function testRetrySnapshotPreservesOriginalPendingAttemptInsteadOfReadingLatestWorkerState(): void
  {
    $operation = $this->operation(AutomationHttpIdempotencyOperationContributionAdapter::definitions(), 'automation_retry_attempt');
    $attempt = new AutomationAttemptView(self::CHILD, self::TARGET, self::ORGANIZATION, 'auto_create_intervention_on_critical_nc', self::RELATED, 2, 'pending', '2026-01-01T00:00:00Z', null, self::ACTOR, null, null, false);
    $history = $this->createMock(AutomationRunHistoryPort::class);
    $history->expects(self::once())->method('reserveRetry')->with(self::ORGANIZATION, self::TARGET, self::RELATED, self::ACTOR)->willReturn(new AutomationRetry($attempt, []));
    $queue = $this->createMock(AutomationRuleQueuePort::class);
    $queue->expects(self::once())->method('enqueue')->with($attempt->ruleKey, self::ORGANIZATION, self::RELATED, [], self::CHILD);
    $policy = $this->createStub(AutomationPolicyPort::class);
    $policy->method('policyFor')->willReturn(new AutomationPolicy(true, []));
    $transactions = $this->createStub(TransactionManagerPort::class);
    $transactions->method('transactional')->willReturnCallback(static fn (callable $work): mixed => $work());
    $handler = new RetryAutomationAttemptHandler($history, $policy, $this->createStub(OrganizationAuthorizationPort::class), $queue, $transactions);
    $original = $handler(new RetryAutomationAttemptCommand(self::ACTOR, self::ORGANIZATION, self::TARGET, self::RELATED));
    $codec = new HttpIdempotencyResultCodec();
    $snapshot = $codec->encode($original, $operation);
    $permissions = ['organization.automation.read', 'organization.automation.manage'];
    [$ordinary, $organizations, $evidence] = $this->authorities($permissions);
    $connection = $this->createStub(Connection::class);
    $connection->method('fetchAssociative')->willReturn(['organization_id' => self::ORGANIZATION, 'rule_key' => $attempt->ruleKey, 'run_id' => self::TARGET, 'status' => 'succeeded']);
    $gate = new AutomationHttpIdempotencyReplayAuthorizationAdapter($ordinary, $organizations, $policy, $connection, $evidence);
    $request = $this->request($operation, ['organizationId' => self::ORGANIZATION, 'runId' => self::TARGET], $this->jsonBody(['attemptId' => self::RELATED]));
    $proof = $this->proof($request, $gate->authorize($request, null), self::TARGET, $permissions);
    $gate->authorize($request, $proof);
    $restored = $codec->decode($snapshot, $operation);
    self::assertInstanceOf(RetryAutomationAttemptResult::class, $restored);
    self::assertEquals($original, $restored);
    self::assertSame('pending', $restored->attempt->status);
    self::assertSame(2, $restored->attempt->attemptNumber);
  }
}
