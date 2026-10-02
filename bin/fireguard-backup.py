#!/usr/bin/env python3
"""Consistent encrypted off-host snapshots and isolated PostgreSQL restore exercises.

Configuration is provisioned privately by the operator; it is never included in
an archive. All command diagnostics are suppressed because providers can echo secrets.
"""
from __future__ import annotations

import argparse
import hashlib
import json
import os
from pathlib import Path
import shutil
import subprocess
import tempfile
import tarfile
import time
import uuid

WRITERS = ["app", "async_worker", "webhook_worker", "assistant_worker", "scheduler_worker"]
OFFHOST_PREFIXES = ("s3:", "sftp:", "rest:https://", "b2:", "azure:", "gs:")


class RecoveryStateError(RuntimeError):
    """A writer recovery needs operator review; retain the installation mutex."""


def run(argv, *, cwd=None, env=None, stdout=subprocess.PIPE, stdin=None, timeout=900):
    """Run a fixed argument vector, keeping provider details out of logs."""
    result = subprocess.run(argv, cwd=cwd, env=env, stdout=stdout, stdin=stdin,
                            stderr=subprocess.PIPE, timeout=timeout, check=False)
    if result.returncode:
        raise RuntimeError("Operation failed; inspect private operator diagnostics.")
    return result.stdout or b""


def config(path: Path):
    """Refuse incomplete prerequisites before stopping any service."""
    if not path.is_file() or path.stat().st_mode & 0o077:
        raise RuntimeError("Backup configuration must exist with mode 0600.")
    value = json.loads(path.read_text())
    repository = value.get("repository", "")
    password = Path(value.get("password_file", ""))
    if not repository.startswith(OFFHOST_PREFIXES) or not password.is_absolute() or not password.is_file():
        raise RuntimeError("An off-host repository and private password file are required.")
    if password.stat().st_mode & 0o077 or not password.stat().st_size:
        raise RuntimeError("The repository password file must be nonempty with mode 0600.")
    if not value.get("secret_recovery_reference"):
        raise RuntimeError("A separately protected secret recovery reference is required.")
    if value.get("storage_mode") not in ("local", "external"):
        raise RuntimeError("Select local or external application storage explicitly.")
    if value["storage_mode"] == "external":
        export = value.get("storage_export_argv")
        if not isinstance(export, list) or not export or not all(isinstance(item, str) for item in export) or not shutil.which(export[0]):
            raise RuntimeError("External storage requires an installed version-consistent export command.")
    credentials = value.get("restic_env", {})
    if not isinstance(credentials, dict) or not all(isinstance(key, str) and isinstance(item, str) for key, item in credentials.items()):
        raise RuntimeError("Provider environment must contain string keys and values.")
    for executable in ("docker", "restic"):
        if not shutil.which(executable):
            raise RuntimeError("Docker and restic must be provisioned before enabling backups.")
    files = value.get("compose_files", ["compose.yaml"])
    if files not in (["compose.yaml"], ["compose.yaml", "compose.dev.yaml"]):
        raise RuntimeError("Unsupported installed Compose topology.")
    return value


def restic_env(value):
    """Only the private operator configuration supplies repository credentials."""
    environment = os.environ.copy()
    environment.update(value.get("restic_env", {}))
    environment.update(RESTIC_REPOSITORY=value["repository"], RESTIC_PASSWORD_FILE=value["password_file"])
    return environment


def compose(value):
    argv = ["docker", "compose"]
    for name in value.get("compose_files", ["compose.yaml"]):
        argv += ["-f", name]
    return argv


def digest(path):
    checksum = hashlib.sha256()
    with path.open("rb") as source:
        for block in iter(lambda: source.read(1024 * 1024), b""):
            checksum.update(block)
    return checksum.hexdigest()


def archive_entries(path):
    """Hash every regular application file, rejecting links and escaping paths."""
    entries = {}
    with tarfile.open(path) as archive:
        for member in archive.getmembers():
            if member.name.startswith("/") or ".." in Path(member.name).parts or member.issym() or member.islnk():
                raise RuntimeError("Unexpected application archive path or link.")
            if member.isfile():
                checksum = hashlib.sha256()
                with archive.extractfile(member) as source:
                    for block in iter(lambda: source.read(1024 * 1024), b""):
                        checksum.update(block)
                entries[member.name] = checksum.hexdigest()
    return entries


def table_counts(command, cwd=None):
    """Observe counts, migration histories and schema names without reading row contents."""
    query = "SELECT format('SELECT %L AS table_name, count(*) AS rows FROM %I.%I;', tablename, schemaname, tablename) FROM pg_tables WHERE schemaname='public' ORDER BY tablename;"
    sql = run(command + ["-tA", "-c", query], cwd=cwd)
    return sql


def counts(command, cwd=None):
    statements = table_counts(command, cwd)
    result = subprocess.run(command + ["-tA"], input=statements, cwd=cwd, stdout=subprocess.PIPE,
                            stderr=subprocess.PIPE, check=False, timeout=120)
    if result.returncode:
        raise RuntimeError("Database count observation failed.")
    return result.stdout.decode().splitlines()


def metrics(app, *, success, started, snapshot=None):
    output = app / "backup-status.prom"
    lines = [f"fireguard_backup_last_attempt_success {int(success)}",
             f"fireguard_backup_last_attempt_timestamp_seconds {int(started)}",
             f"fireguard_backup_last_attempt_duration_seconds {int(time.time() - started)}"]
    previous = output.read_text().splitlines() if output.exists() else []
    lines += ([f"fireguard_backup_last_success_timestamp_seconds {int(time.time())}",
               f"fireguard_backup_last_successful_snapshot_timestamp_seconds {int(started)}"] if success else
              [line for line in previous if line.startswith(("fireguard_backup_last_success_timestamp_seconds ",
                                                            "fireguard_backup_last_successful_snapshot_timestamp_seconds "))])
    temporary = output.with_suffix(".tmp")
    temporary.write_text("\n".join(lines) + "\n")
    temporary.replace(output)


def snapshot(app: Path, value, *, already_stopped=False, source_image=None):
    """Pause all managed writers, snapshot both histories/files, upload encrypted, resume exact state."""
    started = time.time()
    command = compose(value)
    environment = restic_env(value)
    installation_tag = "installation:" + hashlib.sha256(str(app.resolve()).encode()).hexdigest()[:12]
    # A repository probe must pass before any writer is stopped; never initialize it implicitly.
    try:
        run(["restic", "snapshots", "--json"], env=environment)
        storage_probe = 'case "$STORAGE_DSN" in local://var/storage|local:///var/www/html/var/storage) printf local;; s3://*) printf external;; *) exit 78;; esac'
        storage = run(command + ["run", "--rm", "--no-deps", "--entrypoint", "sh", "app", "-c", storage_probe], cwd=app).decode().strip()
        if storage != value["storage_mode"]:
            raise RuntimeError("Backup storage mode must match the application's configured storage.")
        running = run(command + ["ps", "--services", "--filter", "status=running"], cwd=app).decode().splitlines()
        if already_stopped and any(name in running for name in WRITERS):
            raise RuntimeError("Managed writers must be stopped before a deployment snapshot.")
    except (RuntimeError, OSError, subprocess.SubprocessError):
        metrics(app, success=False, started=started)
        raise
    resume = [name for name in WRITERS if name in running] if not already_stopped else []
    snapshot_id = None
    completed = False
    try:
        if not already_stopped:
            run(command + ["stop", "--timeout", "120"] + WRITERS, cwd=app)
        active = run(command + ["ps", "--services", "--filter", "status=running"], cwd=app).decode().splitlines()
        if any(name in active for name in WRITERS):
            raise RuntimeError("Managed writer shutdown could not be confirmed.")
        with tempfile.TemporaryDirectory(prefix="fireguard-snapshot-", dir=app) as temporary:
            stage = Path(temporary)
            manifest = {"format": 1, "created_at": int(started), "consistency": "managed writers stopped",
                        "secret_recovery_reference": value["secret_recovery_reference"], "databases": {}, "files": {}}
            for database in ("auth", "main"):
                service = database + "_database"
                dump = stage / (database + ".dump")
                with dump.open("wb") as output:
                    run(command + ["exec", "-T", service, "sh", "-c", 'pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" -Fc'], cwd=app, stdout=output)
                psql = command + ["exec", "-T", service, "sh", "-c", 'exec psql -v ON_ERROR_STOP=1 -U "$POSTGRES_USER" -d "$POSTGRES_DB" "$@"', "--"]
                manifest["databases"][database] = {"sha256": digest(dump), "bytes": dump.stat().st_size, "table_counts": counts(psql, app)}
            archive = stage / "files.tar"
            with archive.open("wb") as output:
                run(command + ["--profile", "tools", "run", "--rm", "--no-deps", "backup_files"], cwd=app, stdout=output)
            manifest["files"] = {"sha256": digest(archive), "bytes": archive.stat().st_size, "storage_mode": value["storage_mode"], "entries": archive_entries(archive)}
            if value["storage_mode"] == "external":
                export = stage / "external-storage"
                export.mkdir()
                export_env = os.environ.copy()
                export_env["FIREGUARD_EXPORT_DIR"] = str(export)
                run(value["storage_export_argv"], cwd=app, env=export_env)
                if any(item.is_symlink() for item in export.rglob("*")):
                    raise RuntimeError("External storage exports must contain regular files without links.")
                manifest["files"]["external_sha256"] = {str(item.relative_to(export)): digest(item) for item in sorted(export.rglob("*")) if item.is_file()}
            app_id = run(command + ["ps", "--all", "--quiet", "app"], cwd=app).decode().strip()
            if not app_id and not source_image:
                raise RuntimeError("A source image identity is required for the snapshot manifest.")
            manifest["application_image"] = (run(["docker", "inspect", "--format", "{{.Config.Image}}", app_id]).decode().strip() if app_id else source_image)
            identity_command = (["docker", "inspect", "--format", "{{.Image}}", app_id] if app_id else
                                ["docker", "image", "inspect", "--format", "{{.Id}}", source_image])
            manifest["application_image_id"] = run(identity_command).decode().strip()
            (stage / "manifest.json").write_text(json.dumps(manifest, sort_keys=True, indent=2) + "\n")
            result = run(["restic", "backup", "--json", "--tag", "fireguard", "--tag", "consistent", "--tag", installation_tag, str(stage)], env=environment)
            summaries = [json.loads(line) for line in result.decode().splitlines() if line.startswith("{")]
            snapshot_id = next((item["snapshot_id"] for item in summaries if item.get("message_type") == "summary"), None)
            if not snapshot_id:
                raise RuntimeError("Encrypted upload returned no snapshot identity.")
            run(["restic", "forget", "--tag", "fireguard", "--tag", installation_tag, "--group-by", "host,tags", "--keep-daily", "7", "--keep-weekly", "5", "--keep-monthly", "12", "--prune"], env=environment)
            completed = True
    finally:
        try:
            if resume:
                try:
                    run(command + ["start"] + resume, cwd=app)
                    current = run(command + ["ps", "--services", "--filter", "status=running"], cwd=app).decode().splitlines()
                    if set(current).intersection(WRITERS) != set(resume):
                        raise RuntimeError("The previous writer state did not resume.")
                except (RuntimeError, subprocess.SubprocessError):
                    completed = False
                    try:
                        run(command + ["stop", "--timeout", "120"] + WRITERS, cwd=app)
                        current = run(command + ["ps", "--services", "--filter", "status=running"], cwd=app).decode().splitlines()
                        if any(name in current for name in WRITERS):
                            raise RuntimeError("Writer shutdown could not be confirmed.")
                    except (RuntimeError, subprocess.SubprocessError) as error:
                        raise RecoveryStateError("Writer recovery is uncertain; keep the operation lock for review.") from error
                    raise RecoveryStateError("Writer restart failed; all writers stopped and operation lock retained.")
        finally:
            metrics(app, success=completed, started=started, snapshot=snapshot_id)
    print(json.dumps({"snapshot_id": snapshot_id, "duration_seconds": int(time.time() - started)}))


def restore_drill(app: Path, value, snapshot_id, postgres_image):
    """Restore into disposable network-isolated databases; never mount installation volumes."""
    if "@sha256:" not in postgres_image:
        raise RuntimeError("Restore exercises require an immutable PostgreSQL image.")
    started = time.time()
    with tempfile.TemporaryDirectory(prefix="fireguard-restore-drill-") as temporary:
        destination = Path(temporary)
        run(["restic", "restore", snapshot_id, "--target", str(destination)], env=restic_env(value))
        manifests = list(destination.rglob("manifest.json"))
        if len(manifests) != 1:
            raise RuntimeError("A single consistent snapshot manifest is required.")
        stage = manifests[0].parent
        manifest = json.loads(manifests[0].read_text())
        if manifest.get("format") != 1:
            raise RuntimeError("Unsupported snapshot manifest.")
        if digest(stage / "files.tar") != manifest["files"]["sha256"]:
            raise RuntimeError("Application archive checksum mismatch.")
        for relative, expected in manifest["files"].get("external_sha256", {}).items():
            item = stage / "external-storage" / relative
            if ".." in Path(relative).parts or Path(relative).is_absolute() or digest(item) != expected:
                raise RuntimeError("External application file checksum mismatch.")
        restored_files = destination / "restored-application-files"
        restored_files.mkdir()
        with tarfile.open(stage / "files.tar") as archive:
            for member in archive.getmembers():
                if member.name.startswith("/") or ".." in Path(member.name).parts or member.issym() or member.islnk():
                    raise RuntimeError("Unexpected archive path or link.")
                if member.isfile():
                    target = restored_files / member.name
                    target.parent.mkdir(parents=True, exist_ok=True)
                    with archive.extractfile(member) as source, target.open("wb") as output:
                        shutil.copyfileobj(source, output)
                    if digest(target) != manifest["files"]["entries"].get(member.name):
                        raise RuntimeError("Restored application file checksum mismatch.")
        containers = []
        try:
            for database in ("auth", "main"):
                dump = stage / (database + ".dump")
                if digest(dump) != manifest["databases"][database]["sha256"]:
                    raise RuntimeError("Database archive checksum mismatch.")
                name = "fireguard-restore-" + uuid.uuid4().hex[:12] + "-" + database
                run(["docker", "run", "-d", "--name", name, "--network", "none", "--tmpfs", "/var/lib/postgresql/data:rw,size=4g", "-e", "POSTGRES_DB=restore", "-e", "POSTGRES_USER=restore", "-e", "POSTGRES_PASSWORD=isolated-drill-only", postgres_image])
                containers.append(name)
                ready = False
                for _ in range(60):
                    probe = subprocess.run(["docker", "exec", name, "pg_isready", "-U", "restore", "-d", "restore"], capture_output=True, check=False, timeout=10)
                    if not probe.returncode:
                        ready = True
                        break
                    time.sleep(1)
                if not ready:
                    raise RuntimeError("Isolated recovery database did not initialize.")
                with dump.open("rb") as source:
                    run(["docker", "exec", "-i", name, "pg_restore", "-U", "restore", "-d", "restore", "--no-owner", "--no-acl", "--exit-on-error"], stdin=source)
                psql = ["docker", "exec", "-i", name, "psql", "-v", "ON_ERROR_STOP=1", "-U", "restore", "-d", "restore"]
                if counts(psql) != manifest["databases"][database]["table_counts"]:
                    raise RuntimeError("Restored table counts do not match the source snapshot.")
                invalid = run(psql + ["-tA", "-c", "SELECT count(*) FROM pg_constraint WHERE NOT convalidated;"]).decode().strip()
                if invalid != "0":
                    raise RuntimeError("Restored schema contains unvalidated constraints.")
        finally:
            cleanup_failed = False
            for name in containers:
                try:
                    run(["docker", "rm", "-f", name])
                except (RuntimeError, subprocess.SubprocessError):
                    cleanup_failed = True
            if cleanup_failed:
                raise RuntimeError("An isolated recovery container could not be removed.")
        evidence = {"snapshot_id": snapshot_id, "duration_seconds": int(time.time() - started),
                    "database_counts_verified": True, "file_checksum_verified": True,
                    "application_files_restored": len(manifest["files"]["entries"]),
                    "network_isolated": True, "application_smoke_verified": False,
                    "secret_recovery_verified": False}
        (app / "restore-drill.json").write_text(json.dumps(evidence, indent=2) + "\n")
        print(json.dumps(evidence))


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("action", choices=["validate", "snapshot", "restore-drill"])
    parser.add_argument("--config", type=Path, required=True)
    parser.add_argument("--app-dir", type=Path, default=Path.cwd())
    parser.add_argument("--already-stopped", action="store_true")
    parser.add_argument("--lock-held", action="store_true")
    parser.add_argument("--snapshot-id")
    parser.add_argument("--postgres-image")
    parser.add_argument("--source-image")
    args = parser.parse_args()
    os.umask(0o077)
    value = config(args.config)
    if args.action == "validate":
        run(["restic", "snapshots", "--json"], env=restic_env(value))
        return
    lock = args.app_dir / ".fireguard-operation.lock"
    if args.lock_held and not lock.is_dir():
        raise RuntimeError("The installation operation lock must already be held.")
    if not args.lock_held:
        lock.mkdir()
    recovery_review_required = False
    try:
        if args.action == "snapshot":
            snapshot(args.app_dir, value, already_stopped=args.already_stopped, source_image=args.source_image)
        else:
            if not args.snapshot_id or not args.postgres_image:
                raise RuntimeError("Restore exercise requires snapshot ID and immutable PostgreSQL image.")
            restore_drill(args.app_dir, value, args.snapshot_id, args.postgres_image)
    except RecoveryStateError:
        recovery_review_required = True
        raise
    finally:
        if not args.lock_held and not recovery_review_required:
            lock.rmdir()


if __name__ == "__main__":
    try:
        main()
    except (RuntimeError, OSError, ValueError, TypeError, subprocess.SubprocessError):
        print("Backup/recovery operation failed closed; verify private prerequisites and installation state.", file=__import__("sys").stderr)
        raise SystemExit(78)
