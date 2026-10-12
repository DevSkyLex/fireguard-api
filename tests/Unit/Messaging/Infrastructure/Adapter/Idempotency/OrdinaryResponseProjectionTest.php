<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Infrastructure\Adapter\Idempotency;

use Billing\Application\UseCase\Command\CancelSubscription\CancelSubscriptionCommand;
use Billing\Application\UseCase\Query\GetOrganizationSubscription\GetOrganizationSubscriptionQuery;
use Billing\Infrastructure\Adapter\Idempotency\BillingHttpIdempotencyResponseProjectionAdapter;
use DateTimeImmutable;
use Messaging\Application\Contract\Message\MessageView;
use Messaging\Application\UseCase\Command\Message\PostMessage\{PostMessageCommand, PostMessageResult};
use Messaging\Application\UseCase\Query\Message\EnrichMessages\{EnrichMessagesQuery, EnrichMessagesResult};
use Messaging\Infrastructure\Adapter\Idempotency\{MessagingHttpIdempotencyOperationContributionAdapter, MessagingHttpIdempotencyResponseProjectionAdapter};
use PHPUnit\Framework\TestCase;
use Shared\Application\Message\VoidResult;
use Shared\Infrastructure\Idempotency\HttpIdempotencyResultCodec;
use Shared\Infrastructure\Idempotency\ResponseProjection\HttpIdempotencyResponseProjectionCodec;

/**
 * Class OrdinaryResponseProjectionTest
 *
 * The actual codec must accept the exact nested message query schema required by the presentation mapper.
 *
 * @category Test
 */
final class OrdinaryResponseProjectionTest extends TestCase
{
  /**
   * Method testMessageEnrichmentUsesOriginalViewAndMemberAndSerializesUnderDeclaredSchema
   */
  public function testMessageEnrichmentUsesOriginalViewAndMemberAndSerializesUnderDeclaredSchema(): void
  {
    $now = new DateTimeImmutable();
    $view = new MessageView('message', 'conversation', 'organization', 'member', 'test body', [], null, null, null, $now, $now);
    $result = new PostMessageResult($view, 'member');
    $command = new PostMessageCommand('user', 'conversation', 'test body');
    $contributor = new MessagingHttpIdempotencyResponseProjectionAdapter();
    $queries = $contributor->queries('put_message', $command, $result);
    self::assertCount(1, $queries);
    self::assertInstanceOf(EnrichMessagesQuery::class, $queries[0]);
    self::assertSame([$view], $queries[0]->views);
    self::assertSame('member', $queries[0]->currentMemberId);
    $operation = null;
    foreach (MessagingHttpIdempotencyOperationContributionAdapter::definitions() as $candidate) {
      if ('put_message' === $candidate->name) {
        $operation = $candidate;
      }
    }
    self::assertNotNull($operation);
    $projection = $operation->queryProjections[0];
    $codec = new HttpIdempotencyResponseProjectionCodec();
    self::assertNotSame('', $codec->encodeQuery($queries[0], $projection));
    $enrichment = new EnrichMessagesResult([], [], ['message'], ['member' => 'Fixture member']);
    $encoded = $codec->encodeResult($enrichment, $projection);
    self::assertEquals($enrichment, $codec->decodeResult($encoded, $projection));
    $primaryCodec = new HttpIdempotencyResultCodec();
    self::assertEquals($result, $primaryCodec->decode($primaryCodec->encode($result, $operation), $operation));
    self::assertFalse($contributor->supports('unsave_message'));
  }

  /**
   * Method testBillingSubscriptionProjectionKeepsCommandOrganization
   */
  public function testBillingSubscriptionProjectionKeepsCommandOrganization(): void
  {
    $contributor = new BillingHttpIdempotencyResponseProjectionAdapter();
    $queries = $contributor->queries('cancelOrganizationSubscription', new CancelSubscriptionCommand('original-organization'), new VoidResult());
    self::assertCount(1, $queries);
    self::assertInstanceOf(GetOrganizationSubscriptionQuery::class, $queries[0]);
    self::assertSame('original-organization', $queries[0]->organizationId);
    self::assertFalse($contributor->supports('startBillingCheckout'));
  }
}
