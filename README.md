# CloudCart

CloudCart is a PHP 8.2 e-commerce demonstrator for the DAT6007 Cloud Computing assessment. It includes a searchable product catalogue, inventory management, a session-based shopping bag, simulated checkout, order tracking, favourites, password accounts, optional authenticator-app MFA, and optional Microsoft Entra ID sign-in.

## Run locally

Requirements: PHP 8.2 or newer, `pdo_sqlite`, and the PHP built-in web server.

```powershell
php -S 127.0.0.1:8000
```

Open `http://127.0.0.1:8000`. With no Azure SQL settings, CloudCart creates its development database under the user's home data directory. Set `DATA_DIRECTORY` to choose another private location. The initial catalogue is seeded automatically.

For local SQL Server development, set `DB_DRIVER=sqlsrv` and configure `DB_SERVER`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD`. The PHP `sqlsrv` extension must be enabled.

## Azure App Service and Azure SQL

The existing GitHub Actions workflow deploys this application to Azure App Service. Configure these values as App Service settings (and keep passwords in Azure settings or GitHub Actions secrets, never in source control):

| Setting | Purpose |
| --- | --- |
| `MYSHOP_DB_DRIVER` | Set to `sqlsrv`. |
| `SQLSRV_SERVER` | Azure SQL server and port, for example `tcp:example.database.windows.net,1433`. |
| `SQLSRV_DATABASE` | Azure SQL database name. |
| `SQLSRV_UID` | A database user with permission to create the schema or use the pre-created tables. |
| `SQLSRV_PWD` | The database password; configure it as a secret. |
| `ADMIN_EMAIL` | Email address that receives store-administrator access after sign-in. |

The application creates missing tables and seeds the example catalogue on first use. To provision Azure SQL separately, execute [`database/schema.sql`](./database/schema.sql) using an appropriately privileged database account. If tables already exist, apply the schema changes before deploying the application.

Set `ADMIN_EMAIL` before creating the first account for the store administrator. For an existing account, set it and sign in again; that matching address is promoted to administrator.

## Optional Microsoft Entra ID sign-in

Register a single-tenant Microsoft Entra application and add the exact callback URL `https://<your-host>/oauth.php`. Configure these App Service settings:

| Setting | Purpose |
| --- | --- |
| `ENTRA_TENANT_ID` | Entra directory (tenant) ID in GUID format. |
| `ENTRA_CLIENT_ID` | Application (client) ID. |
| `ENTRA_CLIENT_SECRET` | Client secret stored as an Azure secret setting. |
| `ENTRA_REDIRECT_URI` | Exact registered callback URL. |

The sign-in flow uses state, nonce, PKCE, TLS-verified token requests, and Microsoft signing-key verification. Microsoft accounts are not automatically linked to a pre-existing password account with the same email; sign in first and connect the provider from **Your security**.

## Security and payments

- Passwords are stored with PHP's Argon2id algorithm when available, otherwise PHP's current default password algorithm.
- Sign-up and sign-in validate input, rotate session IDs at authentication, use CSRF tokens on state-changing forms, and escape rendered text.
- MFA uses RFC 6238 time-based one-time passwords from a supported authenticator app. Customers enable it in **Your security**; once enabled, every password or Microsoft sign-in also requires a current authenticator code.
- The checkout is deliberately simulated. It accepts no card details, calls no payment provider, and takes no money. Successful demo orders reserve stock in a database transaction; delivery costs £4.95 below £100 and is simulated as free at £100 or more.
- Delivery details are collected only for the demo order, shown only in the customer's account and administrator order view, and should not be populated with real personal data in a publicly shared assessment demo.

## Scaling notes

Use an Azure App Service plan that supports rule-based autoscale and a shared Redis-compatible session store for multi-instance deployments. Set `REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD`, and `REDIS_TLS=1`; the PHP Redis extension is required. CloudCart fails explicitly if Redis is configured but unavailable rather than silently reverting to per-instance file sessions.

Place Azure Front Door in front of App Service for edge caching of `/assets/*`, `styles.css`, and `store.js`, HTTPS termination, and origin load balancing. Keep checkout, account, admin, and order-status responses private and uncached. Azure SQL remains the shared transactional data tier. Configure autoscale limits and database capacity against measured load and budget; this repository does not provision billable Azure resources or claim that a live load test has been run.

## Assessment scope

This repository implements the application deliverable. The separate 3,000-word report and deployed-application link required by the brief are assessment submissions and must be prepared and verified separately. The app provides a multi-tier PHP/SQL Server design and a REST-style JSON order-status endpoint; SOAP/REST comparison, architecture diagrams, cloud performance evidence, and citations belong in the report.
