CREATE TABLE IF NOT EXISTS leads
(
    uuid UUID,
    organization_id UUID,
    acceptance_id UUID,
    email String,
    phone String,
    uniq_id String,
    status LowCardinality(String),
    price_cents Int64,
    created_at DateTime64(6, 'UTC'),
    updated_at DateTime64(6, 'UTC')
)
ENGINE = ReplacingMergeTree(updated_at)
PARTITION BY toYYYYMM(created_at)
ORDER BY (organization_id, uuid)
SETTINGS index_granularity = 8192;
