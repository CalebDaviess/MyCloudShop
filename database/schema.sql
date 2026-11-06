IF OBJECT_ID(N'dbo.users', N'U') IS NULL
CREATE TABLE dbo.users (
    id INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
    name NVARCHAR(120) NOT NULL,
    email NVARCHAR(254) NOT NULL,
    password_hash NVARCHAR(255) NOT NULL,
    mfa_secret NVARCHAR(64) NULL,
    mfa_enabled BIT NOT NULL CONSTRAINT df_users_mfa_enabled DEFAULT 0,
    oauth_subject NVARCHAR(160) NULL,
    is_admin BIT NOT NULL CONSTRAINT df_users_is_admin DEFAULT 0,
    created_at DATETIME2 NOT NULL CONSTRAINT df_users_created_at DEFAULT SYSUTCDATETIME(),
    CONSTRAINT uq_users_email UNIQUE (email)
);

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'ux_users_oauth_subject' AND object_id = OBJECT_ID(N'dbo.users'))
CREATE UNIQUE INDEX ux_users_oauth_subject ON dbo.users(oauth_subject) WHERE oauth_subject IS NOT NULL;

IF OBJECT_ID(N'dbo.products', N'U') IS NULL
CREATE TABLE dbo.products (
    id INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
    name NVARCHAR(120) NOT NULL,
    description NVARCHAR(1000) NOT NULL,
    category NVARCHAR(60) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    stock INT NOT NULL CONSTRAINT df_products_stock DEFAULT 0,
    image NVARCHAR(255) NOT NULL,
    is_featured BIT NOT NULL CONSTRAINT df_products_featured DEFAULT 0,
    is_active BIT NOT NULL CONSTRAINT df_products_active DEFAULT 1,
    created_at DATETIME2 NOT NULL CONSTRAINT df_products_created_at DEFAULT SYSUTCDATETIME()
);

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'ix_products_catalogue' AND object_id = OBJECT_ID(N'dbo.products'))
CREATE INDEX ix_products_catalogue ON dbo.products(is_active, category, is_featured, id);

IF OBJECT_ID(N'dbo.orders', N'U') IS NULL
CREATE TABLE dbo.orders (
    id BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
    user_id INT NOT NULL,
    status NVARCHAR(30) NOT NULL,
    total DECIMAL(10,2) NOT NULL,
    delivery_fee DECIMAL(10,2) NOT NULL CONSTRAINT df_orders_delivery_fee DEFAULT 0,
    payment_reference NVARCHAR(40) NOT NULL,
    delivery_name NVARCHAR(120) NOT NULL,
    delivery_address NVARCHAR(300) NOT NULL,
    created_at DATETIME2 NOT NULL CONSTRAINT df_orders_created_at DEFAULT SYSUTCDATETIME(),
    updated_at DATETIME2 NOT NULL CONSTRAINT df_orders_updated_at DEFAULT SYSUTCDATETIME(),
    CONSTRAINT uq_orders_payment_reference UNIQUE (payment_reference),
    CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES dbo.users(id)
);

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'ix_orders_user_created' AND object_id = OBJECT_ID(N'dbo.orders'))
CREATE INDEX ix_orders_user_created ON dbo.orders(user_id, created_at DESC, id DESC);

IF OBJECT_ID(N'dbo.order_items', N'U') IS NULL
CREATE TABLE dbo.order_items (
    id BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
    order_id BIGINT NOT NULL,
    product_id INT NOT NULL,
    product_name NVARCHAR(120) NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    CONSTRAINT fk_items_order FOREIGN KEY (order_id) REFERENCES dbo.orders(id),
    CONSTRAINT fk_items_product FOREIGN KEY (product_id) REFERENCES dbo.products(id)
);

IF OBJECT_ID(N'dbo.wishlist_items', N'U') IS NULL
CREATE TABLE dbo.wishlist_items (
    id INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    created_at DATETIME2 NOT NULL CONSTRAINT df_wishlist_created_at DEFAULT SYSUTCDATETIME(),
    CONSTRAINT uq_wishlist_user_product UNIQUE (user_id, product_id),
    CONSTRAINT fk_wishlist_user FOREIGN KEY (user_id) REFERENCES dbo.users(id),
    CONSTRAINT fk_wishlist_product FOREIGN KEY (product_id) REFERENCES dbo.products(id)
);
