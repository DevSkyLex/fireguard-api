# Config Review Checklist

- `security.yaml` does not widen access unintentionally
- `rate_limiter.yaml` still protects abuse cases
- test config remains deterministic without masking production behavior
- `SECURITY.md` is updated if guarantees changed
