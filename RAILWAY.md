# Railway deploy

## 1. Export old data

Export the current InfinityFree database through phpMyAdmin.

Use the real working database export, not `database.sql`.
`database.sql` is only a fresh empty schema and should not be imported over existing data.

## 2. Create Railway services

1. Create a new Railway project.
2. Add a MySQL database service.
3. Add this PHP app as another service.

Railway should use the `Dockerfile` from this project.

## 3. Configure app variables

In the PHP app service, add these variables from the Railway MySQL service:

- `MYSQL_URL`

Or add these variables individually:

- `MYSQLHOST`
- `MYSQLPORT`
- `MYSQLUSER`
- `MYSQLPASSWORD`
- `MYSQLDATABASE`

After Railway gives you the public app URL, you may also set:

- `APP_URL`
- `ADMIN_USERNAME`
- `ADMIN_PASSWORD`

The app also supports manual fallback variable names:

- `DB_HOST`
- `DB_PORT`
- `DB_USER`
- `DB_PASS`
- `DB_NAME`

## 4. Import database

Import the exported InfinityFree `.sql` file into the Railway MySQL database.

Do not import `database.sql` unless you intentionally want an empty fresh database.

## 5. Check

Open:

- `/ping.html`
- `/`
- `/admin/login.php`
