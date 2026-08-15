# ClassCheck

ClassCheck is a teacher-first classroom attendance and assessment application. It turns a real seating layout into the working interface for enrollment, roll call, activities, quizzes, exams, and reports.

## Included workflows

- Teacher registration, verification, login, password reset, and account settings
- Subject sections with academic terms, schedules, and archives
- Grouped row-by-column classroom blocks with disabled chair positions
- Interactive desk-chair map with student details and seat reassignment
- Manual roster entry and CSV roster import
- Private, local QR generation for student chair claiming
- Default-present attendance with chair toggles and automatic hour summaries
- Activities, quizzes, and exams with keyboard-first score entry
- Absent-student skipping with an explicit score override
- Gradebook, CSV exports, print views, and private file attachments

## Local setup

Requirements: PHP 8.3+, Composer, Node 22+, npm, PostgreSQL 16+, and PHP's
`pdo_pgsql` extension.

Create a local PostgreSQL database before running the application:

```sql
CREATE DATABASE classcheck;
```

```powershell
composer install
npm.cmd install
Copy-Item .env.example .env
# Set DB_USERNAME and DB_PASSWORD in .env for your PostgreSQL installation.
php artisan key:generate
php artisan migrate
npm.cmd run build
composer run dev
```

Open `http://localhost:8000`.

For a populated local demonstration:

```powershell
php artisan db:seed
```

The non-production demo account is:

- Email: `teacher@classcheck.test`
- Password: `password`

The demo seeder is disabled when `APP_ENV=production`.

## CSV roster format

Use a UTF-8 CSV file with these headers:

```csv
student_number,first_name,middle_name,last_name
2026-001,Andrea,,Reyes
2026-002,Ben,M.,Cruz
```

Student numbers are unique within each section. Existing student numbers are updated/matched instead of duplicated.

## Verification

```powershell
php artisan test
npx.cmd vue-tsc --noEmit
npm.cmd run build
composer audit --no-interaction
npm.cmd audit --omit=dev
```

## Production deployment

### Vercel

Vercel builds this application from `Dockerfile.vercel`. The container installs
Composer dependencies before compiling the Vue assets and listens on Vercel's
`PORT`.

In Vercel under **Settings > Build and Deployment**, set the Framework Preset
to **Services**. Disable any existing Build Command and Output Directory
overrides left by the earlier Vite configuration. The `vercel.json` file
declares the Laravel container service and routes every request to it; Vercel
must be in Services mode for that configuration to take effect.

Configure these variables in the Vercel project:

```dotenv
APP_NAME=ClassCheck
APP_ENV=production
APP_KEY=base64:replace-with-php-artisan-key-generate-show
APP_DEBUG=false
APP_URL=https://your-project.vercel.app
LOG_CHANNEL=stderr
DB_CONNECTION=pgsql
DATABASE_URL=postgresql://user:password@host/database?sslmode=require
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
SESSION_SECURE_COOKIE=true
```

Install **Neon Postgres** from the Vercel Marketplace so Vercel provisions the
database and injects its pooled `DATABASE_URL` (or a prefixed
`DB_DATABASE_URL`). The connection URL must use `sslmode=require`. The container refuses to start
without that shared PostgreSQL connection and runs pending migrations before it
accepts requests.

The users, sections, sessions, cache, and queued jobs all use Neon. This is
required because Vercel containers are stateless; SQLite changes and locally
uploaded files do not persist across instances or deployments. Configure
external object storage before relying on student image or assessment
attachment uploads in production.

### Traditional server

- Serve the Laravel `public/` directory through HTTPS.
- Set `APP_ENV=production`, `APP_DEBUG=false`, and the public `APP_URL` used in QR links.
- Configure PostgreSQL and production mail credentials in `.env`. Use
  `DB_SSLMODE=require` when the server requires TLS.
- Run `php artisan migrate --force` and `npm.cmd run build` during deployment.
- Run `php artisan optimize` after environment values are final.
- Keep `storage/` and `bootstrap/cache/` writable by the PHP process.
- Back up the database and private storage together.

Student photos and assessment attachments are stored privately and are served only through teacher-authorized routes.
