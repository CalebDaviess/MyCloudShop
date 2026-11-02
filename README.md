# MyCloudShop

MyShop registration demo built with PHP and SQL Server.

## Run locally

Start the PHP development server from the repository root:

```sh
php -S 0.0.0.0:8000
```

Open `http://localhost:8000`.

## Registration database

The app connects directly to Azure SQL. In Azure App Service, set `DB_SERVER`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` as environment variables to match your Azure SQL server, database, and login. The code has defaults for the first three values, but those defaults must match your actual setup. Do not put the password in source control. Restart the App Service after changing settings.

The App Service PHP runtime must provide Microsoft's `sqlsrv` PHP extension and ODBC Driver 18. The Azure SQL database must contain a `shopusers` table with `name`, `email`, and `password` columns. Passwords are stored using PHP's `password_hash()`. The deployment workflow deploys the app but does not manage database credentials.
