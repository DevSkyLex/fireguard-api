<?php

declare(strict_types=1);

namespace OAuth\Presentation\Api\Serialization\Decoder;

use OAuth\Domain\Exception\Token\AuthorizationException;
use Symfony\Component\Serializer\Encoder\DecoderInterface;

use function count;
use function explode;
use function is_string;
use function parse_str;
use function preg_match;
use function urldecode;

/**
 * Decodes OAuth's flat scalar form parameters without PHP name aliases or nested credential arrays.
 *
 * @category Decoder
 */
final readonly class OAuthFormDecoder implements DecoderInterface
{
  /**
   * @param array<string, mixed> $context serializer context
   *
   * @return array<string, string> exact scalar OAuth form parameters
   */
  public function decode(string $data, string $format, array $context = []): array
  {
    if (!$this->supportsDecoding($format) || 1 === preg_match('/%(?![0-9a-fA-F]{2})/', $data)) {
      throw AuthorizationException::invalidRequest('Malformed OAuth form encoding.');
    }
    $pairs = explode('&', $data);
    if (count($pairs) > 64) {
      throw AuthorizationException::invalidRequest('Too many OAuth form parameters.');
    }
    $names = [];
    foreach ($pairs as $pair) {
      if ('' === $pair) {
        continue;
      }
      $name = urldecode(explode('=', $pair, 2)[0]);
      if (1 !== preg_match('/^(?=[\x00-\x7F]*$)[a-zA-Z]\w*$/D', $name) || isset($names[$name])) {
        throw AuthorizationException::invalidRequest('OAuth form parameters must have unique scalar names.');
      }
      $names[$name] = true;
    }
    parse_str($data, $fields);
    $decoded = [];
    foreach ($fields as $name => $value) {
      if (!is_string($name) || !is_string($value)) {
        throw AuthorizationException::invalidRequest('OAuth form parameters must be scalar strings.');
      }
      $decoded[$name] = $value;
    }

    return $decoded;
  }

  public function supportsDecoding(string $format): bool
  {
    return 'form' === $format;
  }
}
