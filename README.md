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

The existing GitHub Actions workflow deploys this application to Azure App Service. It configures the PHP 8.2 runtime. The static `index.html` entry point redirects to `home.php`, which loads the CloudCart home page; this avoids the current App Service Nginx routing issue with the `index.php` path. Configure these values as App Service settings (and keep passwords in Azure settings or GitHub Actions secrets, never in source control):

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

## Azure Functions order notifications

The optional Python Azure Function in [`azure_function/`](./azure_function/) receives a JSON notification after CloudCart has committed a demo order. It validates the order ID, total, currency, and item details, then acknowledges the event. The notification deliberately excludes customer names, email addresses, delivery addresses, and payment details. The sample does not send emails or write to another database; add those downstream actions to the function if needed.

### Deploy the Function

Create a separate Linux Function App in Azure with the Python 3.12 runtime, Functions runtime version 4, and a storage account configured as `AzureWebJobsStorage`. The storage account is required by the Functions host. The Function App must be in a resource group accessible to the GitHub Actions Azure identity already used by this repository.

The repository includes a manual deployment workflow. In GitHub, open **Actions → Deploy CloudCart Azure Function → Run workflow**, enter the existing Function App name and resource group, and run it. The workflow configures the Python 3.12/Functions v4 runtime and remote build, deploys `azure_function/`, retrieves the function key, sets `CLOUDCART_ORDER_FUNCTION_URL` and `CLOUDCART_ORDER_FUNCTION_KEY` on the CloudCart App Service, and performs a synthetic HTTP smoke test. It does not create Azure resources. If you prefer Azure Functions Core Tools, from the `azure_function` directory:

```powershell
python -m venv .venv
.\.venv\Scripts\Activate.ps1
pip install -r requirements.txt
func azure functionapp publish <FUNCTION_APP_NAME> --build remote
```

The function endpoint is `https://<FUNCTION_APP_NAME>.azurewebsites.net/api/neworder`. If you deploy manually, open the function's **Function Keys** in Azure Portal and copy a function-specific key, then configure these App Service settings on the CloudCart PHP app:

| Setting | Purpose |
| --- | --- |
| `CLOUDCART_ORDER_FUNCTION_URL` | The HTTPS URL ending in `/api/neworder`. |
| `CLOUDCART_ORDER_FUNCTION_KEY` | The function-specific key, stored as a secret App Service setting. |

CloudCart sends the key in the `x-functions-key` header. Keep both values out of source control and logs. The PHP cURL extension is required for this optional integration. If the function URL is not configured, checkout behaves as before. If notification delivery fails after an order is committed, the order remains recorded, the customer sees a warning, and the failure is written to the PHP error log; checkout is not rolled back. This workshop example is best-effort and does not provide durable retries. Use an outbox/queue for production delivery guarantees, and make downstream consumers idempotent using the stable `event_id`.

To run the Function locally, install Azure Functions Core Tools and start the host from `azure_function`:

```powershell
func start
```

Set `CLOUDCART_ORDER_FUNCTION_URL` to the local `/api/neworder` endpoint and `CLOUDCART_ORDER_FUNCTION_KEY` to a local function key in the same environment as the PHP server. Then place a demo order in CloudCart and check the Functions host output for the accepted order event. Do not use real customer data for the assessment demo.

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
