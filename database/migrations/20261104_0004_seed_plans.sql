-- F2S Pro — plans and quotas (MANIFEST §2). Starting values, to be tuned after the pilot
-- with a NEW migration (UPDATE plans …), never by editing this file.

INSERT INTO plans (code, name, price_cents, currency, billing_period, limits) VALUES
('free', 'Gratuit', 0, 'USD', 'none',
 '{"published_campaigns_rolling_90_days": 1, "active_campaigns": 1, "questions_per_form": 30, "responses_per_campaign": 100, "ai_credits": 10, "dashboard_level": 2, "ai_report": "preview", "imports": ["xlsx_simple", "csv_simple"], "exports": ["csv", "json"], "collaborators": 1, "watermark": true}'),
('project_pass', 'Pass Projet', 500, 'USD', '30_days',
 '{"active_campaigns": 1, "questions_per_form": 150, "responses_per_campaign": 1000, "ai_credits": 60, "dashboard_level": 6, "ai_report": "full", "imports": ["all"], "exports": ["csv", "json", "xlsx", "pdf"], "collaborators": 1, "watermark": false}'),
('pro', 'Pro', 1000, 'USD', 'monthly',
 '{"active_campaigns": 10, "questions_per_form": 300, "responses_per_month": 5000, "ai_credits_per_month": 100, "dashboard_level": 6, "ai_report": "full", "imports": ["all", "connectors"], "exports": ["csv", "json", "xlsx", "pdf", "xlsform"], "collaborators": 2, "watermark": false}'),
('pro_yearly', 'Pro (annuel)', 9600, 'USD', 'yearly',
 '{"active_campaigns": 10, "questions_per_form": 300, "responses_per_month": 5000, "ai_credits_per_month": 100, "dashboard_level": 6, "ai_report": "full", "imports": ["all", "connectors"], "exports": ["csv", "json", "xlsx", "pdf", "xlsform"], "collaborators": 2, "watermark": false}');
