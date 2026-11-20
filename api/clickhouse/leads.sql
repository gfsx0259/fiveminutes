CREATE TABLE IF NOT EXISTS leads
(
    uuid UUID,
    organization_id UUID,
    buyer_id UUID,
    seller_id Nullable(UUID),
    acceptance_id Nullable(UUID),
    order_id Nullable(UUID),
    email String,
    phone String,
    first_name String,
    last_name String,
    country LowCardinality(String),
    traffic_source LowCardinality(String),
    age LowCardinality(String),
    gender LowCardinality(String),
    ip String,
    aff_sub String,
    uniq_id String,
    status LowCardinality(String),
    price_cents Int64,
    created_at DateTime64(6, 'UTC'),
    updated_at DateTime64(6, 'UTC'),
    sold_at Nullable(DateTime64(6, 'UTC'))
)
ENGINE = ReplacingMergeTree(updated_at)
PARTITION BY toYYYYMM(created_at)
ORDER BY (organization_id, uuid)
SETTINGS index_granularity = 8192;

ALTER TABLE leads ADD COLUMN IF NOT EXISTS buyer_id UUID DEFAULT '00000000-0000-0000-0000-000000000000';
ALTER TABLE leads ADD COLUMN IF NOT EXISTS seller_id Nullable(UUID);
ALTER TABLE leads ADD COLUMN IF NOT EXISTS first_name String DEFAULT '';
ALTER TABLE leads ADD COLUMN IF NOT EXISTS last_name String DEFAULT '';
ALTER TABLE leads ADD COLUMN IF NOT EXISTS country LowCardinality(String) DEFAULT '';
ALTER TABLE leads ADD COLUMN IF NOT EXISTS ip String DEFAULT '';
ALTER TABLE leads ADD COLUMN IF NOT EXISTS aff_sub String DEFAULT '';
ALTER TABLE leads MODIFY COLUMN acceptance_id Nullable(UUID);
ALTER TABLE leads ADD COLUMN IF NOT EXISTS sold_at Nullable(DateTime64(6, 'UTC'));
ALTER TABLE leads ADD COLUMN IF NOT EXISTS order_id Nullable(UUID);
ALTER TABLE leads ADD COLUMN IF NOT EXISTS traffic_source LowCardinality(String) DEFAULT '';
ALTER TABLE leads ADD COLUMN IF NOT EXISTS age LowCardinality(String) DEFAULT '';
ALTER TABLE leads ADD COLUMN IF NOT EXISTS gender LowCardinality(String) DEFAULT '';
ALTER TABLE leads ADD COLUMN IF NOT EXISTS is_delivered UInt8 DEFAULT 0;
ALTER TABLE leads ADD COLUMN IF NOT EXISTS delivery_error UInt8 DEFAULT 0;
ALTER TABLE leads ADD COLUMN IF NOT EXISTS client_status String DEFAULT '';
ALTER TABLE leads ADD COLUMN IF NOT EXISTS client_deposit UInt8 DEFAULT 0;
