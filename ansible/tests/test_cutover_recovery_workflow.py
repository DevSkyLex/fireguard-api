"""Verify that reviewed cutover recovery keeps the ordinary deployment gates."""
from pathlib import Path
import shlex
import unittest

import yaml


ROOT = Path(__file__).resolve().parents[2]


class CutoverRecoveryWorkflowTest(unittest.TestCase):
    def setUp(self):
        self.workflow = yaml.safe_load((ROOT / '.github/workflows/deploy-vps.yml').read_text())
        self.play = yaml.safe_load((ROOT / 'ansible/deploy.yml').read_text())[0]
        self.tasks = self.play['tasks']

    def test_recovery_follows_source_ci_validation_with_read_only_guard_permissions(self):
        guard = self.workflow['jobs']['guard']
        names = [step.get('name') for step in guard['steps']]
        self.assertLess(names.index('Verify exact CI and SonarQube gate'),
                        names.index('Verify the reviewed development cutover recovery request'))
        self.assertEqual('read', guard['permissions']['actions'])
        self.assertEqual('read', guard['permissions']['contents'])
        self.assertNotIn('pull_request_target', self.workflow.get('on', self.workflow.get(True, {})))
        recovery = next(step for step in guard['steps'] if step.get('id') == 'recovery')
        self.assertEqual('${{ inputs.recover_cutover_run_id }}', recovery['env']['RECOVER_CUTOVER_RUN_ID'])
        self.assertNotIn('VPS_SSH_KEY', recovery['env'])
        self.assertNotIn('VPS_HOST', recovery['env'])

    def test_explicit_recovery_never_replaces_ordinary_atomic_lock(self):
        names = [task.get('name') for task in self.tasks]
        recovered = names.index('Recover only the verified empty failed development cutover lock')
        acquired = names.index('Acquire the installation operation lock before changing runtime configuration')
        self.assertLess(recovered, acquired)
        self.assertLess(acquired, names.index('Copy base Docker Compose file'))
        self.assertNotIn('when', self.tasks[acquired])
        self.assertEqual(['mkdir', '{{ fireguard_app_dir }}/.fireguard-operation.lock'],
                         self.tasks[acquired]['ansible.builtin.command']['argv'])
        self.assertEqual("fireguard_cutover_recovery_allowed == 'true'", self.tasks[recovered]['when'])
        self.assertNotIn('failed_when', self.tasks[recovered])
        self.assertNotIn('ignore_errors', self.tasks[recovered])

    def test_recovery_is_scoped_to_verified_development_installation_and_metadata(self):
        task = next(task for task in self.tasks if task.get('name') ==
                    'Validate a requested cutover recovery before ordinary lock acquisition')
        checks = task['ansible.builtin.assert']['that']
        self.assertIn("fireguard_cutover_recovery_allowed != 'true' or fireguard_deployment_environment == 'development'", checks)
        self.assertIn("fireguard_cutover_recovery_allowed != 'true' or fireguard_app_dir is match('^/srv/apps/fireguard/development/back$')", checks)
        self.assertIn("fireguard_cutover_recovery_allowed != 'true' or fireguard_cutover_failed_source_sha is match('^[0-9a-f]{40}$')", checks)
        deploy = self.workflow['jobs']['deploy']
        self.assertIn('needs.guard.result', deploy['if'])
        self.assertEqual('${{ needs.guard.outputs.cutover-recovery-allowed || \'false\' }}',
                         deploy['env']['CUTOVER_RECOVERY_ALLOWED'])
        for suffix in ['SOURCE_SHA', 'START', 'END', 'RUN_ID']:
            self.assertIn('needs.guard.outputs.', deploy['env']['CUTOVER_FAILED_' + suffix])

    def test_helpers_are_isolated_from_installed_runtime_and_do_not_reset_fixtures(self):
        task = next(task for task in self.tasks if task.get('name') ==
                    'Install reviewed helpers without changing the installed runtime')
        self.assertEqual(['recover-cutover-lock.py', 'webhook-cutover.py'], task['loop'])
        self.assertIn('/.fireguard-cutover-recovery/', task['ansible.builtin.copy']['dest'])
        self.assertEqual("fireguard_cutover_recovery_allowed == 'true'", task['when'])
        command = next(task for task in self.tasks if task.get('name') ==
                       'Recover only the verified empty failed development cutover lock')['ansible.builtin.command']['argv']
        self.assertNotIn('--already-drained=true', command)
        self.assertNotIn('--reset', command)
        for option in ['--failed-source-sha', '--failed-run-id', '--failed-start', '--failed-end', '--repository']:
            self.assertIn(option, command)

    def test_ci_passes_all_deployment_tests_to_one_unittest_command(self):
        ci = yaml.safe_load((ROOT / '.github/workflows/ci.yml').read_text())
        commands = [step['run'] for job in ci['jobs'].values() for step in job.get('steps', [])
                    if step.get('working-directory') == 'ansible/tests' and 'unittest' in step.get('run', '')]
        self.assertEqual(1, len(commands))
        self.assertNotIn('\n', commands[0])
        arguments = shlex.split(commands[0])
        self.assertEqual(['python3', '-B', '-m', 'unittest', '-v'], arguments[:5])
        self.assertEqual(['test_topology', 'test_deployment_maintenance', 'test_ssh_host_trust',
                          'test_idempotency_validation', 'test_cutover_recovery',
                          'test_cutover_recovery_workflow'], arguments[5:])


if __name__ == '__main__':
    unittest.main()
