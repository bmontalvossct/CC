# Database Data Preservation Rule

## Policy
- **Never remove or delete data in the database automatically.**
- If any code, feature, refactoring, or migration requires deleting, resetting, pruning, or truncating database records, **always ask for user confirmation first**.
- Do not run `migrate:fresh`, `migrate:reset`, `db:wipe`, or destructive database purge commands without explicit user permission.
