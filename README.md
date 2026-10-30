# MyCloudShop

MyShop registration demo built with PHP and SQL Server.

## Run locally

Start the PHP development server from the repository root:

```sh
php -S 0.0.0.0:8000
```

Open `http://localhost:8000`.

## Registration database

Registration requires PHP's Microsoft SQL Server drivers (`sqlsrv`) and these environment variables:

- `SQLSRV_SERVER`: SQL Server host/instance
- `SQLSRV_DATABASE`: database name
- `SQLSRV_UID`: database username
- `SQLSRV_PWD`: database password

The configured database must contain a `shopusers` table with `name`, `email`, and `password` columns. Passwords are stored using PHP's `password_hash()`. Never commit database credentials; `.env` is ignored by Git.
