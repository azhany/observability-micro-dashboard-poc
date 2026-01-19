<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Alert Firing</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .alert-box {
            background-color: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 4px;
            padding: 15px;
            margin: 20px 0;
        }
        .alert-title {
            color: #856404;
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .details {
            margin: 15px 0;
        }
        .detail-row {
            padding: 5px 0;
        }
        .label {
            font-weight: bold;
            display: inline-block;
            width: 150px;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            font-size: 12px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Alert Notification</h1>

        <div class="alert-box">
            <div class="alert-title">🔥 Alert is FIRING</div>

            <div class="details">
                <div class="detail-row">
                    <span class="label">Tenant:</span>
                    <span>{{ $tenant->name }}</span>
                </div>
                <div class="detail-row">
                    <span class="label">Metric:</span>
                    <span>{{ $alert->alertRule->metric_name }}</span>
                </div>
                <div class="detail-row">
                    <span class="label">Current Value:</span>
                    <span>{{ $metric->value }}</span>
                </div>
                <div class="detail-row">
                    <span class="label">Threshold:</span>
                    <span>{{ $alert->alertRule->operator }} {{ $alert->alertRule->threshold }}</span>
                </div>
                <div class="detail-row">
                    <span class="label">Started At:</span>
                    <span>{{ $alert->started_at->format('Y-m-d H:i:s T') }}</span>
                </div>
                <div class="detail-row">
                    <span class="label">Alert ID:</span>
                    <span>{{ $alert->id }}</span>
                </div>
            </div>
        </div>

        <p>This alert was triggered because the metric <strong>{{ $alert->alertRule->metric_name }}</strong>
        has exceeded the configured threshold.</p>

        <div class="footer">
            <p>This is an automated notification from the Observability Dashboard.</p>
        </div>
    </div>
</body>
</html>
