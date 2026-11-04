-- F2S Pro — temporary free-plan limits requested by the project owner (2026-11-04),
-- until the users & plans control console exists: 70 questions per form,
-- 5 published campaigns (open at once and per rolling 90 days).
UPDATE plans
SET limits = JSON_SET(limits,
        '$.questions_per_form', 70,
        '$.published_campaigns_rolling_90_days', 5,
        '$.active_campaigns', 5),
    updated_at = UTC_TIMESTAMP()
WHERE code = 'free';
