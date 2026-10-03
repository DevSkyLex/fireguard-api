<?php

declare(strict_types=1);

use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use Facility\Domain\ValueObject\GlbDocument;
use Tests\Helper\GlbFixture;

/**
 * Socket HTTP smoke test for the GLB multipart limit, using private test database clones.
 * Run after make test-db: php bin/check-facility-model-http.php.
 *
 * @category Validation
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
$_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = 'test';
$_SERVER['APP_DEBUG'] = $_ENV['APP_DEBUG'] = '0';
$_SERVER['MFA_ENABLED'] = $_ENV['MFA_ENABLED'] = 'false';
require dirname(__DIR__) . '/tests/bootstrap.php';

$project = dirname(__DIR__);
$kernel = new Kernel('test', false);
$kernel->boot();
$authManager = $kernel->getContainer()->get('doctrine')->getManager('auth');
assert($authManager instanceof EntityManagerInterface);
$authDatabase = $authManager->getConnection()->getDatabase();
if (!is_string($authDatabase) || !preg_match('/^fireguard_auth_test_w[A-Za-z0-9_]+$/', $authDatabase)) {
  throw new RuntimeException('HTTP smoke requires a private cloned auth test database.');
}
$updated = $authManager->getConnection()->executeStatement(
  'UPDATE users SET password = :password WHERE email = :email',
  ['password' => password_hash('SocketFixture123!', PASSWORD_BCRYPT), 'email' => 'admin@fireguard.local'],
);
if (1 !== $updated) {
  throw new RuntimeException('The private auth database is missing its admin fixture.');
}
$manager = $kernel->getContainer()->get('doctrine')->getManager('main');
assert($manager instanceof EntityManagerInterface);
if (!preg_match('/^fireguard_main_test_w[A-Za-z0-9_]+$/', (string) $manager->getConnection()->getDatabase())) {
  throw new RuntimeException('HTTP smoke requires a private cloned main test database.');
}
$building = $manager->getConnection()->fetchAssociative(
  "SELECT id, organization_id FROM facilities WHERE type = 'building' AND record_status = 'published' AND organization_id = :organization ORDER BY id LIMIT 1",
  ['organization' => '11111111-1111-4111-8111-111111111111'],
);
if (false === $building) {
  throw new RuntimeException('Run make test-db to seed a published building.');
}
$kernel->shutdown();
$socket = stream_socket_server('tcp://127.0.0.1:0');
if (false === $socket) {
  throw new RuntimeException('Could not reserve a local test port.');
}
$address = stream_socket_get_name($socket, false);
fclose($socket);
if (!is_string($address)) {
  throw new RuntimeException('Could not determine the local test port.');
}
$environment = getenv();
foreach ($_ENV as $key => $value) {
  if (is_string($value)) {
    $environment[$key] = $value;
  }
}
// The child receives real environment overrides; reloading Dotenv must not replace private clones.
unset($environment['SYMFONY_DOTENV_VARS']);
$log = tempnam(sys_get_temp_dir(), 'fg-glb-http-log-');
$file = tempnam(sys_get_temp_dir(), 'fg-glb-http-file-');
$router = tempnam(sys_get_temp_dir(), 'fg-glb-http-router-');
if (false === $log || false === $file || false === $router) {
  throw new RuntimeException('Could not prepare isolated test files.');
}
// Boot the real HTTP kernel with the parent's isolated environment. The CLI
// server must not re-run the PHPUnit bootstrap and clone a different fixture DB.
file_put_contents($router, <<<'PHP'
<?php
foreach (getenv() as $key => $value) {
  $_SERVER[$key] = $_ENV[$key] = $value;
}
require getcwd() . '/vendor/autoload.php';
$kernel = new \App\Kernel('test', false);
$request = \Symfony\Component\HttpFoundation\Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
PHP);
$process = proc_open(
  [PHP_BINARY, '-d', 'upload_max_filesize=12M', '-d', 'post_max_size=16M', '-d', 'memory_limit=256M',
    '-S', $address, '-t', 'public', $router],
  [0 => ['pipe', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']],
  $pipes,
  $project,
  $environment,
  ['bypass_shell' => true],
);
if (!is_resource($process)) {
  throw new RuntimeException('Could not start the local PHP HTTP server.');
}
$base = 'http://' . $address;

/**
 * @param list<string> $headers
 * @param array<string, CURLFile>|string|null $body
 *
 * @return array{status: int, body: string}
 */
function facility_model_http_request(string $url, string $method, array $headers, array|string|null $body = null): array
{
  $request = curl_init($url);
  curl_setopt_array($request, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method,
    CURLOPT_HTTPHEADER => $headers, CURLOPT_TIMEOUT => 60, CURLOPT_CONNECTTIMEOUT => 2]);
  if (null !== $body) {
    curl_setopt($request, CURLOPT_POSTFIELDS, $body);
  }
  $response = curl_exec($request);
  if (!is_string($response)) {
    throw new RuntimeException('Local HTTP request failed: ' . curl_error($request));
  }
  $status = (int) curl_getinfo($request, CURLINFO_RESPONSE_CODE);
  curl_close($request);

  return ['status' => $status, 'body' => $response];
}

try {
  $ready = false;
  for ($attempt = 0; $attempt < 100; ++$attempt) {
    $connection = @stream_socket_client('tcp://' . $address, $errorNumber, $errorMessage, 0.1);
    if (is_resource($connection)) {
      fclose($connection);
      $ready = true;

      break;
    }
    usleep(100000);
  }
  if (!$ready) {
    throw new RuntimeException('Local HTTP server did not start.');
  }
  $login = facility_model_http_request(
    $base . '/api/auth/login',
    'POST',
    ['Content-Type: application/ld+json'],
    json_encode(['email' => 'admin@fireguard.local', 'password' => 'SocketFixture123!'], JSON_THROW_ON_ERROR),
  );
  $token = json_decode($login['body'], true, 512, JSON_THROW_ON_ERROR)['access_token'] ?? null;
  if (!is_string($token) || !in_array($login['status'], [200, 201], true)) {
    $problem = json_decode($login['body'], true, 512, JSON_THROW_ON_ERROR);

    throw new RuntimeException('Fixture login failed with HTTP ' . $login['status'] . ': ' . (is_string($problem['detail'] ?? null) ? $problem['detail'] : 'No detail'));
  }
  $headers = ['Authorization: Bearer ' . $token, 'Accept: application/ld+json'];
  $collection = $base . '/api/organizations/' . $building['organization_id'] . '/facilities/' . $building['id'] . '/models';
  $document = GlbFixture::document();
  $document['extras'] = ['padding' => ''];
  $document['extras']['padding'] = str_repeat(' ', GlbDocument::MAX_BYTES - strlen(GlbFixture::contents($document)));
  $contents = GlbFixture::contents($document);
  if (GlbDocument::MAX_BYTES !== strlen($contents)) {
    throw new RuntimeException('The upload fixture must be exactly 10 MiB.');
  }
  file_put_contents($file, $contents);
  $upload = facility_model_http_request($collection, 'POST', $headers, ['file' => new CURLFile($file, 'model/gltf-binary', 'boundary.glb')]);
  if (201 !== $upload['status']) {
    throw new RuntimeException('10 MiB upload rejected with HTTP ' . $upload['status']);
  }
  $model = json_decode($upload['body'], true, 512, JSON_THROW_ON_ERROR);
  if (GlbDocument::MAX_BYTES !== ($model['fileSize'] ?? null) || !is_string($model['id'] ?? null)) {
    throw new RuntimeException('The uploaded file size or model identifier is invalid.');
  }
  $download = facility_model_http_request($base . '/api/facility-models/' . $model['id'] . '/download', 'GET', $headers);
  if (200 !== $download['status'] || hash('sha256', $contents) !== hash('sha256', $download['body'])) {
    throw new RuntimeException('Authenticated download changed the immutable uploaded bytes.');
  }
  file_put_contents($file, $contents . "\0\0\0\0");
  $oversize = facility_model_http_request($collection, 'POST', $headers, ['file' => new CURLFile($file, 'model/gltf-binary', 'oversized.glb')]);
  if (422 !== $oversize['status']) {
    throw new RuntimeException('Oversized file should produce HTTP 422, received ' . $oversize['status']);
  }
  file_put_contents($file, 'corrupt GLB content bytes');
  $corrupt = facility_model_http_request($collection, 'POST', $headers, ['file' => new CURLFile($file, 'model/gltf-binary', 'corrupt.glb')]);
  if (422 !== $corrupt['status']) {
    throw new RuntimeException('Corrupt file should produce HTTP 422, received ' . $corrupt['status']);
  }
  $deleted = facility_model_http_request($base . '/api/facility-models/' . $model['id'], 'DELETE', [...$headers, 'If-Match: "revision-1"']);
  if (204 !== $deleted['status']) {
    throw new RuntimeException('Uploaded test model cleanup failed.');
  }
  fwrite(STDOUT, "HTTP GLB smoke passed: 10 MiB upload 201, immutable authenticated download 200, oversized/corrupt 422, cleanup 204.\n");
} finally {
  proc_terminate($process);
  foreach ($pipes as $pipe) {
    if (is_resource($pipe)) {
      fclose($pipe);
    }
  }
  proc_close($process);
  unlink($file);
  unlink($log);
  unlink($router);
}
