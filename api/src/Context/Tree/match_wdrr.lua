-- Weighted deficit round-robin over one preset pool.
-- KEYS[1]  {org}:preset:{id}:orders
-- ARGV    dow, minute, utc, ttl, alpha, prefix, geo, today, mode
local pool_key = KEYS[1]
local now_dow = tonumber(ARGV[1])
local now_min = tonumber(ARGV[2])
local utc_ts = tonumber(ARGV[3])
local daily_ttl = tonumber(ARGV[4])
local alpha = tonumber(ARGV[5])
local key_prefix = ARGV[6] or ''
local geo = ARGV[7] or ''
local today = ARGV[8] or ''
local mode = ARGV[9] or 'day'

local function order_data_key(order_id)
    return key_prefix .. 'order:' .. order_id .. ':data'
end

local function local_day()
    return math.floor(utc_ts / 86400)
end

local function order_sold_key(order_id)
    return key_prefix .. 'order:' .. order_id .. ':sold:' .. tostring(local_day())
end

local function is_available(availability_utc)
    if availability_utc == nil or availability_utc == false or availability_utc == '' then
        return true
    end

    for segment in string.gmatch(availability_utc, '([^,]+)') do
        local dow, start_min, end_min = string.match(segment, '^(%d+):(%d+)-(%d+)$')
        if dow ~= nil and tonumber(dow) == now_dow then
            start_min = tonumber(start_min)
            end_min = tonumber(end_min)
            if now_min >= start_min and now_min < end_min then
                return true
            end
        end
    end

    return false
end

local function weight_pow(rate)
    local value = tonumber(rate)
    if value == nil or value <= 0 then
        return 0.0
    end
    if alpha == nil or alpha <= 0 then
        return value
    end
    return value ^ alpha
end

local function try_candidate(order_id)
    local fields = redis.call(
        'HMGET',
        order_data_key(order_id),
        'partner_id',
        'rate',
        'availability_utc',
        'capacity',
        'geo',
        'date',
        'stock'
    )
    local partner_id = fields[1]
    local rate = fields[2]
    local availability_utc = fields[3]
    local capacity = fields[4]
    local order_geo = fields[5]
    local order_date = fields[6]
    local stock = fields[7]

    if partner_id == false or rate == false or order_geo ~= geo or not is_available(availability_utc) then
        return nil
    end

    if mode == 'stock' then
        if stock ~= '1' then
            return nil
        end
    else
        if stock ~= '0' or order_date ~= today then
            return nil
        end
    end

    local sold = tonumber(redis.call('GET', order_sold_key(order_id)) or '0')
    local cap = tonumber(capacity) or 0
    cap = cap - sold
    if cap <= 0 then
        return nil
    end

    local weight = cap * weight_pow(rate)
    if weight <= 0 then
        return nil
    end

    return {
        orderId = order_id,
        partner_id = partner_id,
        rate = tonumber(rate) or 0,
        sold = sold,
        weight = weight,
    }
end

if redis.call('EXISTS', pool_key) == 0 then
    return 'POOL_NOT_FOUND'
end

local order_ids = redis.call('SMEMBERS', pool_key)
if order_ids == nil or #order_ids == 0 then
    return nil
end

local candidates = {}
local sum_weight = 0.0
local total_sold = 0

for index = 1, #order_ids do
    local candidate = try_candidate(order_ids[index])
    if candidate ~= nil then
        total_sold = total_sold + candidate.sold
        sum_weight = sum_weight + candidate.weight
        table.insert(candidates, candidate)
    end
end

if #candidates == 0 or sum_weight <= 0 then
    return nil
end

local best
local best_deficit = -1e18
local round = total_sold + 1

for index = 1, #candidates do
    local candidate = candidates[index]
    local fair = round * (candidate.weight / sum_weight)
    local deficit = fair - candidate.sold
    if deficit > best_deficit then
        best_deficit = deficit
        best = candidate
    end
end

if best == nil then
    return nil
end

local sold_key = order_sold_key(best.orderId)
local count = redis.call('INCR', sold_key)
if count == 1 then
    redis.call('EXPIRE', sold_key, daily_ttl)
end

return {
    best.orderId,
    best.partner_id,
    tostring(best.rate),
    tostring(local_day()),
}
