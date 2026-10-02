<?php

declare(strict_types=1);

// A container-local heartbeat records receiver-loop progress rather than PID liveness.
// Read no deployment environment file and print no message payloads.
$path = sys_get_temp_dir() . '/fireguard-worker-heartbeat.json';
$maxAge = (int) (getenv('WORKER_HEARTBEAT_MAX_AGE') ?: 300);
$heartbeat = is_readable($path) ? json_decode((string) file_get_contents($path), true) : null;
if (!is_array($heartbeat) || !is_int($heartbeat['timestamp'] ?? null)
  || !is_int($heartbeat['pid'] ?? null) || $maxAge < 1
  || time() - $heartbeat['timestamp'] > $maxAge || $heartbeat['timestamp'] > time()
  || !is_file('/proc/' . $heartbeat['pid'] . '/cmdline')) {
  fwrite(STDERR, "Consumer receiver heartbeat is missing or stale.\n");
  exit(1);
}
if (in_array('--sweeps', $argv, true)) {
  $baselinePath = __DIR__ . '/../var/worker-sweeps/monitoring-start.timestamp';
  $baseline = is_readable($baselinePath) ? (int) file_get_contents($baselinePath) : 0;
  $limits = [
    'RecomputeMaintenanceSchedulesCommand' => 7200,
    'MaterializeDueRecurrencesCommand' => 7200,
    'ExpireStaleApprovalRequestsCommand' => 7200,
    'EscalateNonConformitySlaBreachesCommand' => 7200,
    'VerifyOrganizationDomainsCommand' => 172800,
    'SendWeeklyDigestsCommand' => 691200,
  ];
  foreach ($limits as $name => $limit) {
    $marker = __DIR__ . '/../var/worker-sweeps/' . $name . '.timestamp';
    $timestamp = is_readable($marker) ? (int) file_get_contents($marker) : $baseline;
    if ($timestamp < 1 || $timestamp > time() || time() - $timestamp > $limit) {
      fwrite(STDERR, "A scheduled sweep has no recent successful completion.\n");
      exit(1);
    }
  }
}
exit(0);
