# MyCloudShop

MyShop registration demo built with PHP and SQL Server.

## Run locally

Start the PHP development server from the repository root:

```sh
php -S 0.0.0.0:8000
```

Open `http://localhost:8000`.

## Registration database

Registration uses SQLite by default, which requires PHP's `pdo_sqlite` extension (available in the Codespace). The app creates `data/myshop.sqlite` and the `shopusers` table automatically. This local database file is ignored by Git.

On Azure App Service, the deployment workflow configures the SQL Server driver selection and non-secret connection settings. Add `SQLSRV_PWD` as an App Service environment variable (never in source control), then restart the app. Outside Azure, set `MYSHOP_DB_DRIVER=sqlsrv` and configure these environment variables:

- `SQLSRV_SERVER`: SQL Server host/instance
- `SQLSRV_DATABASE`: database name
- `SQLSRV_UID`: database username
- `SQLSRV_PWD`: database password

The SQL Server database must contain a `shopusers` table with `name`, `email`, and `password` columns. The App Service Linux PHP runtime must also provide Microsoft's `sqlsrv` PHP extension and ODBC Driver 18. For SQLite, set `MYSHOP_DB_PATH` to override the default database file location. Passwords are stored using PHP's `password_hash()`. Never commit database credentials; `.env` is ignored by Git.
