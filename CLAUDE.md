# Permanent Instructions & Database Safety Rules

> [!CAUTION]
> ### CRITICAL: Database Data Deletion Policy
> - **DO NOT remove or delete ANY data in the database.**
> - If any tasks, features, migrations, scripts, refactorings, or code implementations require deleting, pruning, truncating, or dropping data in the database, **YOU MUST ASK AND GET EXPLICIT PERMISSION FROM THE USER FIRST**.
> - **Strictly Prohibited Without Prior User Consent**:
>   - Running destructive Artisan commands like `migrate:fresh`, `migrate:reset`, `db:wipe`, or database truncation scripts.
>   - Writing migrations that drop tables or drop existing columns containing data.
>   - Hard deleting records or executing batch delete/cleanup queries on existing user data.
> - Always seek user confirmation before proceeding with any destructive database operation.
