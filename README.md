# MyCloudShop

MyShop registration demo built with PHP and SQL Server.

## Run locally

Start the PHP development server from the repository root:

```sh
php -S 0.0.0.0:8000
```

Open `http://localhost:8000`.

## Registration database

The app connects directly to Azure SQL using the server, database, and login configured in `process_register.php`. Set `DB_PASSWORD` in Azure App Service environment variables; do not put the password in source control. Restart the App Service after adding the setting.

The App Service PHP runtime must provide Microsoft's `sqlsrv` PHP extension and ODBC Driver 18. The Azure SQL database must contain a `shopusers` table with `name`, `email`, and `password` columns. Passwords are stored using PHP's `password_hash()`. The deployment workflow deploys the app but does not manage database credentials.
