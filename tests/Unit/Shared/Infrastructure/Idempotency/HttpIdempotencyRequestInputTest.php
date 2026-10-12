<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Idempotency;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\Idempotency\{HmacIdempotencyFingerprintAdapter, HttpIdempotencyRequestInput};
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;

use function base64_encode;
use function file_put_contents;
use function str_repeat;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

/**
 * Class HttpIdempotencyRequestInputTest
 *
 * Verifies fingerprints bind request meaning while excluding JSON key ordering and multipart boundaries.
 *
 * @category Adapter Tests
 */
#[CoversClass(HttpIdempotencyRequestInput::class)]
final class HttpIdempotencyRequestInputTest extends TestCase
{
  #[Test]
  public function rawFormRequestsBindTheirFieldsWithoutDependingOnPhpPostBagPopulation(): void
  {
    $raw = Request::create('/api/token', 'POST', server: ['CONTENT_TYPE' => 'application/x-www-form-urlencoded'], content: 'grant_type=client_credentials&scope=openid+profile&client_id=client');
    $reordered = Request::create('/api/token', 'POST', server: ['CONTENT_TYPE' => 'application/x-www-form-urlencoded'], content: 'client_id=client&scope=openid%20profile&grant_type=client_credentials');
    $parsed = Request::create('/api/token', 'POST', parameters: ['grant_type' => 'client_credentials', 'scope' => 'openid profile', 'client_id' => 'client'], server: ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);
    self::assertSame($this->digest($raw), $this->digest($reordered));
    self::assertSame($this->digest($raw), $this->digest($parsed));
    $changed = Request::create('/api/token', 'POST', server: ['CONTENT_TYPE' => 'application/x-www-form-urlencoded'], content: 'grant_type=client_credentials&scope=other&client_id=client');
    self::assertNotSame($this->digest($raw), $this->digest($changed));
  }

  #[Test]
  public function itIgnoresJsonMapOrderButPreservesNullPresenceListOrderAndContainerType(): void
  {
    self::assertSame($this->digest($this->json('{"name":"x","description":null}')), $this->digest($this->json('{"description":null,"name":"x"}')));
    self::assertNotSame($this->digest($this->json('{"name":"x"}')), $this->digest($this->json('{"name":"x","description":null}')));
    self::assertNotSame($this->digest($this->json('{"items":[1,2]}')), $this->digest($this->json('{"items":[2,1]}')));
    self::assertNotSame($this->digest($this->json('{"properties":{}}')), $this->digest($this->json('{"properties":[]}')));
    self::assertNotSame($this->digest($this->json('')), $this->digest($this->json('null')));
    self::assertNotSame($this->digest($this->json('{"0":"x"}')), $this->digest($this->json('["x"]')));
  }

  #[Test]
  public function itBindsTheOriginalPreconditionsQueryAndRepresentation(): void
  {
    $first = $this->json('{}');
    $first->headers->set('If-Match', '"revision-3"');
    $same = $this->json('{}');
    $same->headers->set('If-Match', '"revision-3"');
    self::assertSame($this->digest($first), $this->digest($same));
    $same->headers->set('If-Match', '"revision-4"');
    self::assertNotSame($this->digest($first), $this->digest($same));
    $same->headers->set('If-Match', '"revision-3"');
    $same->query->set('dryRun', '1');
    self::assertNotSame($this->digest($first), $this->digest($same));
    $same->query->remove('dryRun');
    $same->headers->set('Accept', 'application/ld+json');
    self::assertNotSame($this->digest($first), $this->digest($same));
    $same->headers->set('Accept', $first->headers->get('Accept', ''));
    $same->headers->set('Accept-Language', 'fr');
    self::assertNotSame($this->digest($first), $this->digest($same));
    $same->headers->set('Accept-Language', $first->headers->get('Accept-Language', ''));
    $same->headers->set('Idempotency-Key', 'another-key');
    self::assertSame($this->digest($first), $this->digest($same));
  }

  #[Test]
  public function itBindsMultipartFieldsAndActualFileBytesWithoutTheTransportBoundary(): void
  {
    $firstPath = tempnam(sys_get_temp_dir(), 'fg-http-input-');
    $secondPath = tempnam(sys_get_temp_dir(), 'fg-http-input-');
    self::assertIsString($firstPath);
    self::assertIsString($secondPath);

    try {
      file_put_contents($firstPath, "first\0binary\xff");
      file_put_contents($secondPath, "other\0binary\xff");
      $first = $this->multipart($firstPath, 'first-boundary', 'proof');
      $same = $this->multipart($firstPath, 'different-boundary', 'proof');
      self::assertSame($this->digest($first), $this->digest($same));
      self::assertNotSame($this->digest($first), $this->digest($this->multipart($secondPath, 'first-boundary', 'proof')));
      self::assertNotSame($this->digest($first), $this->digest($this->multipart($firstPath, 'first-boundary', 'invoice')));
    } finally {
      unlink($firstPath);
      unlink($secondPath);
    }
  }

  #[Test]
  public function itRejectsOversizedBodiesBeforeParsing(): void
  {
    $fingerprints = new HmacIdempotencyFingerprintAdapter(base64_encode(str_repeat('x', 32)));
    $input = new HttpIdempotencyRequestInput($fingerprints, maximumBodyBytes: 2);
    $this->expectException(InvalidArgumentException::class);
    $input->canonical($this->json('{"name":"x"}'));
  }

  private function json(string $body): Request
  {
    return Request::create('/api/resources/id', 'PATCH', server: ['CONTENT_TYPE' => 'application/merge-patch+json'], content: $body);
  }

  private function multipart(string $path, string $boundary, string $label): Request
  {
    return Request::create('/api/resources/id/attachments', 'POST', parameters: ['label' => $label], files: [
      'file' => new UploadedFile($path, 'proof.pdf', 'application/pdf', test: true),
    ], server: ['CONTENT_TYPE' => 'multipart/form-data; boundary=' . $boundary], content: 'irrelevant raw framing');
  }

  private function digest(Request $request): string
  {
    $fingerprints = new HmacIdempotencyFingerprintAdapter(base64_encode(str_repeat('x', 32)));

    return $fingerprints->digest('http.input', new HttpIdempotencyRequestInput($fingerprints)->canonical($request));
  }
}
