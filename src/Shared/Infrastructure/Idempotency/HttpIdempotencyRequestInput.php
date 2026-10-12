<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Idempotency;

use InvalidArgumentException;
use Shared\Application\Port\Outbound\Idempotency\IdempotencyFingerprintPort;
use stdClass;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;

use function array_map;
use function explode;
use function get_object_vars;
use function hash;
use function hash_file;
use function is_array;
use function json_decode;
use function parse_str;
use function strlen;
use function strtolower;
use function trim;

use const JSON_THROW_ON_ERROR;

/**
 * Class HttpIdempotencyRequestInput
 *
 * Canonicalizes request meaning before dispatch without retaining raw multipart bytes or transport boundaries.
 * JSON object/list identity, missing fields, explicit nulls and ordered lists remain distinguishable.
 *
 * @category Adapter
 */
final readonly class HttpIdempotencyRequestInput
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param IdempotencyFingerprintPort $fingerprints the dedicated purpose-separated HMAC capability
   * @param int $maximumBodyBytes the maximum JSON or textual body size to parse
   * @param int $maximumFileBytes the per-file bound before streaming its fingerprint
   *
   * @return void
   */
  public function __construct(
    private IdempotencyFingerprintPort $fingerprints,
    private int $maximumBodyBytes = 8388608,
    private int $maximumFileBytes = 104857600,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method canonical
   *
   * Includes parsed query values, representation headers and the original consumed preconditions.
   * The opaque idempotency key is a separate protocol input and never enters this document.
   *
   * @access public
   *
   * @param Request $request the current HTTP request before processors consume uploads
   *
   * @return array<string, mixed> finite canonical request meaning
   */
  public function canonical(Request $request): array
  {
    $mediaType = strtolower(trim(explode(';', $request->headers->get('Content-Type', ''), 2)[0]));

    return [
      'version' => 1,
      'method' => $request->getMethod(),
      'query' => $request->query->all(),
      'representation' => [
        'accept' => $request->headers->get('Accept'),
        'acceptLanguage' => $request->headers->get('Accept-Language'),
        'contentType' => $mediaType,
        'prefer' => $request->headers->get('Prefer'),
      ],
      'preconditions' => [
        'ifMatch' => $request->headers->get('If-Match'),
        'ifNoneMatch' => $request->headers->get('If-None-Match'),
      ],
      'body' => 'multipart/form-data' === $mediaType
        ? ['kind' => 'multipart', 'fields' => $request->request->all(), 'files' => $this->files($request->files->all())]
        : $this->body($request, $mediaType),
    ];
  }

  /**
   * Method body
   *
   * @access private
   *
   * @param Request $request the request containing JSON or another explicit wire format
   * @param string $mediaType the normalized media type without boundary parameters
   *
   * @return array<string, mixed> the body meaning with absent and JSON container identity retained
   */
  private function body(Request $request, string $mediaType): array
  {
    $body = $request->getContent();
    if (strlen($body) > $this->maximumBodyBytes) {
      throw new InvalidArgumentException('Idempotency request body exceeds its byte budget.');
    }
    if ('application/x-www-form-urlencoded' === $mediaType) {
      $fields = $request->request->all();
      if ('' !== $body) {
        parse_str($body, $fields);
      }

      return ['kind' => 'form', 'fields' => $fields];
    }
    if ('' === $body) {
      return ['kind' => 'absent'];
    }

    return 'application/json' === $mediaType || 'application/merge-patch+json' === $mediaType || 'application/ld+json' === $mediaType
      ? ['kind' => 'json', 'value' => $this->jsonValue(json_decode($body, false, 64, JSON_THROW_ON_ERROR))]
      : ['kind' => 'opaque', 'digest' => $this->fingerprints->digest('http.body', ['sha256' => hash('sha256', $body), 'size' => strlen($body)])];
  }

  /**
   * Method jsonValue
   *
   * Tags objects before converting them to PHP arrays so empty and numerically named objects stay objects.
   *
   * @access private
   *
   * @param mixed $value the decoded JSON value
   *
   * @return mixed scalar, list or tagged object meaning
   */
  private function jsonValue(mixed $value): mixed
  {
    if ($value instanceof stdClass) {
      $fields = [];
      foreach (get_object_vars($value) as $key => $item) {
        $fields[$key] = $this->jsonValue($item);
      }

      return ['kind' => 'object', 'fields' => $fields];
    }
    if (is_array($value)) {
      return array_map($this->jsonValue(...), $value);
    }

    return $value;
  }

  /**
   * Method files
   *
   * Streams every uploaded file and HMACs its content digest with metadata used by the upload command.
   * Nested file fields retain their keys and list ordering; temporary paths never enter the fingerprint.
   *
   * @access private
   *
   * @param array<array-key, mixed> $files the parsed file bag or one nested file-field group
   *
   * @return array<array-key, mixed> HMAC file identities in the original field structure
   */
  private function files(array $files): array
  {
    $result = [];
    foreach ($files as $field => $file) {
      if (is_array($file)) {
        $result[$field] = $this->files($file);
      } elseif ($file instanceof UploadedFile) {
        $size = $file->getSize();
        if (false === $size || $size > $this->maximumFileBytes || !$file->isValid()) {
          throw new InvalidArgumentException('Idempotency upload is invalid or exceeds its byte budget.');
        }
        $contentDigest = hash_file('sha256', $file->getPathname());
        if (false === $contentDigest) {
          throw new InvalidArgumentException('Idempotency upload bytes are unavailable.');
        }
        $result[$field] = $this->fingerprints->digest('multipart.file', [
          'sha256' => $contentDigest,
          'size' => $size,
          'originalName' => $file->getClientOriginalName(),
          'mimeType' => $file->getClientMimeType(),
        ]);
      } elseif (null === $file) {
        $result[$field] = null;
      } else {
        throw new InvalidArgumentException('Idempotency upload field is invalid.');
      }
    }

    return $result;
  }
  // #endregion
}
