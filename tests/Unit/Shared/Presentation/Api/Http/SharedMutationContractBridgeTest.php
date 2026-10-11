<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Presentation\Api\Http;

use Calendar\Presentation\Api\Http\MergePatchFields as CalendarMergePatchFields;
use Equipment\Presentation\Api\Http\{MergePatchFields as EquipmentMergePatchFields, RevisionGuard as EquipmentRevisionGuard};
use Facility\Presentation\Api\Http\{MergePatchFields as FacilityMergePatchFields, RevisionGuard as FacilityRevisionGuard};
use Inspection\Presentation\Api\Http\{MergePatchFields as InspectionMergePatchFields, RevisionGuard as InspectionRevisionGuard};
use Intervention\Presentation\Api\Http\{MergePatchFields as InterventionMergePatchFields, RevisionGuard as InterventionRevisionGuard};
use Messaging\Presentation\Api\Http\RevisionGuard as MessagingRevisionGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Presentation\Api\Http\{MergePatchFields as SharedMergePatchFields, RevisionGuard as SharedRevisionGuard};
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{PreconditionFailedHttpException, PreconditionRequiredHttpException};

use function sprintf;

/**
 * Class SharedMutationContractBridgeTest.
 *
 * Keeps every module's transport facade compatible with the pure mutation contracts.
 *
 * @category HTTP Contract Tests
 */
final class SharedMutationContractBridgeTest extends TestCase
{
  // #region Methods
  /**
   * Method testPatchFacadesPreserveTransportAndFieldPresence.
   *
   * @param class-string<CalendarMergePatchFields|EquipmentMergePatchFields|FacilityMergePatchFields|InspectionMergePatchFields|InterventionMergePatchFields|SharedMergePatchFields> $facade the owner-local facade
   * @param array<string, mixed> $expected the retained field values
   */
  #[DataProvider('patchRequests')]
  public function testPatchFacadesPreserveTransportAndFieldPresence(string $facade, ?string $method, string $contents, array $expected): void
  {
    $stack = new RequestStack();
    if (null !== $method) {
      $stack->push(Request::create('/api/resource/id', $method, content: $contents));
    }

    self::assertSame($expected, new $facade($stack)->all());
  }

  /**
   * Method patchRequests.
   *
   * @return iterable<string, array{class-string<CalendarMergePatchFields|EquipmentMergePatchFields|FacilityMergePatchFields|InspectionMergePatchFields|InterventionMergePatchFields|SharedMergePatchFields>, ?string, string, array<string, mixed>}> the request cases
   */
  public static function patchRequests(): iterable
  {
    $facades = [CalendarMergePatchFields::class, EquipmentMergePatchFields::class, FacilityMergePatchFields::class, InspectionMergePatchFields::class, InterventionMergePatchFields::class, SharedMergePatchFields::class];
    $cases = [
      'explicit null and nested values' => ['PATCH', '{"name":null,"settings":{"0":true},"list":[1,2]}', ['name' => null, 'settings' => [true], 'list' => [1, 2]]],
      'numeric object key' => ['PATCH', '{"0":"first","name":null}', []],
      'JSON array' => ['PATCH', '["first"]', []],
      'JSON scalar' => ['PATCH', '"name"', []],
      'JSON null' => ['PATCH', 'null', []],
      'malformed document' => ['PATCH', '{"name":', []],
      'empty document' => ['PATCH', '', []],
      'empty object' => ['PATCH', '{}', []],
      'empty array' => ['PATCH', '[]', []],
      'POST remains ignored' => ['POST', '{"name":null}', []],
      'PUT remains ignored' => ['PUT', '{"name":null}', []],
      'no current request' => [null, '{"name":null}', []],
    ];
    foreach ($facades as $facade) {
      foreach ($cases as $name => [$method, $contents, $expected]) {
        yield $facade . ' ' . $name => [$facade, $method, $contents, $expected];
      }
    }
  }

  /**
   * Method testRevisionFacadesPreserveSuccessfulParsingAndMatching.
   *
   * @param class-string<EquipmentRevisionGuard|FacilityRevisionGuard|InspectionRevisionGuard|InterventionRevisionGuard|MessagingRevisionGuard|SharedRevisionGuard> $facade the owner-local facade
   */
  #[DataProvider('validRevisions')]
  public function testRevisionFacadesPreserveSuccessfulParsingAndMatching(string $facade, string $ifMatch, int $revision): void
  {
    $stack = new RequestStack();
    $request = Request::create('/api/resource/id', 'PATCH');
    $request->headers->set('If-Match', $ifMatch);
    $stack->push($request);
    $guard = new $facade($stack);

    self::assertSame($revision, $guard->expectedRevision());
    $guard->assertMatches($revision);
  }

  /**
   * Method validRevisions.
   *
   * @return iterable<string, array{class-string<EquipmentRevisionGuard|FacilityRevisionGuard|InspectionRevisionGuard|InterventionRevisionGuard|MessagingRevisionGuard|SharedRevisionGuard>, string, int}> the valid revision cases
   */
  public static function validRevisions(): iterable
  {
    foreach (self::revisionFacades() as $facade) {
      yield $facade . ' zero' => [$facade, '"revision-0"', 0];
      yield $facade . ' current revision' => [$facade, '"revision-7"', 7];
      yield $facade . ' leading zeroes' => [$facade, '"revision-007"', 7];
    }
  }

  /**
   * Method testRevisionFacadesKeepErrorClassStatusMessageAndHeaders.
   *
   * @param class-string<EquipmentRevisionGuard|FacilityRevisionGuard|InspectionRevisionGuard|InterventionRevisionGuard|MessagingRevisionGuard|SharedRevisionGuard> $facade the owner-local facade
   * @param class-string<PreconditionFailedHttpException|PreconditionRequiredHttpException> $exception the historical HTTP error class
   */
  #[DataProvider('refusedRevisions')]
  public function testRevisionFacadesKeepErrorClassStatusMessageAndHeaders(string $facade, ?string $ifMatch, string $exception, int $status, string $message): void
  {
    $stack = new RequestStack();
    $request = Request::create('/api/resource/id', 'PATCH');
    if (null !== $ifMatch) {
      $request->headers->set('If-Match', $ifMatch);
    }
    $stack->push($request);

    try {
      new $facade($stack)->assertMatches(7);
      self::fail('The refused conditional mutation must raise its HTTP error.');
    } catch (PreconditionFailedHttpException|PreconditionRequiredHttpException $failure) {
      self::assertInstanceOf($exception, $failure);
      self::assertSame($status, $failure->getStatusCode());
      self::assertSame($message, $failure->getMessage());
      self::assertSame([], $failure->getHeaders());
    }
  }

  /**
   * Method refusedRevisions.
   *
   * @return iterable<string, array{class-string<EquipmentRevisionGuard|FacilityRevisionGuard|InspectionRevisionGuard|InterventionRevisionGuard|MessagingRevisionGuard|SharedRevisionGuard>, ?string, class-string<PreconditionFailedHttpException|PreconditionRequiredHttpException>, int, string}> the refused revision cases
   */
  public static function refusedRevisions(): iterable
  {
    foreach (self::revisionFacades() as $facade) {
      yield $facade . ' absent' => [$facade, null, PreconditionRequiredHttpException::class, 428, 'If-Match is required for this mutation.'];
      yield $facade . ' empty' => [$facade, '', PreconditionRequiredHttpException::class, 428, 'If-Match is required for this mutation.'];
      $invalid = ['*', 'W/"revision-7"', 'revision-7', '"revision-7", "revision-8"', ' "revision-7"', '"revision-7" ', '"revision--1"', '"revision-7.0"', '"Revision-7"', '"revision-6"', ' '];
      foreach ($invalid as $index => $ifMatch) {
        yield sprintf('%s invalid or stale %d', $facade, $index) => [$facade, $ifMatch, PreconditionFailedHttpException::class, 412, 'The resource revision is stale.'];
      }
    }
  }

  /**
   * Method revisionFacades.
   *
   * @return list<class-string<EquipmentRevisionGuard|FacilityRevisionGuard|InspectionRevisionGuard|InterventionRevisionGuard|MessagingRevisionGuard|SharedRevisionGuard>> the revision facade classes
   */
  private static function revisionFacades(): array
  {
    return [EquipmentRevisionGuard::class, FacilityRevisionGuard::class, InspectionRevisionGuard::class, InterventionRevisionGuard::class, MessagingRevisionGuard::class, SharedRevisionGuard::class];
  }
  // #endregion
}
