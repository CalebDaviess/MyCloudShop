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

To use SQL Server instead, install Microsoft's PHP SQL Server driver and set `MYSHOP_DB_DRIVER=sqlsrv` along with these environment variables:

- `SQLSRV_SERVER`: SQL Server host/instance
- `SQLSRV_DATABASE`: database name
- `SQLSRV_UID`: database username
- `SQLSRV_PWD`: database password

The SQL Server database must contain a `shopusers` table with `name`, `email`, and `password` columns. For SQLite, set `MYSHOP_DB_PATH` to override the default database file location. Passwords are stored using PHP's `password_hash()`. Never commit database credentials; `.env` is ignored by Git.
