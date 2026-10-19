<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attachment Not Found &bull; B2B Email Outbound</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #f8fafc;
            color: #334155;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
        }
        .error-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.01);
            max-width: 520px;
            width: 100%;
            padding: 40px 32px;
            text-align: center;
        }
        .icon-circle {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background-color: #fef3c7;
            color: #d97706;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin-bottom: 20px;
        }
        .title {
            font-size: 20px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 8px;
        }
        .message {
            font-size: 14px;
            color: #64748b;
            line-height: 1.6;
            margin-bottom: 24px;
        }
        .notice-box {
            background: #f1f5f9;
            border-radius: 8px;
            padding: 14px 16px;
            font-size: 13px;
            color: #475569;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="error-card">
        <div class="icon-circle">
            <i class="bi bi-file-earmark-x"></i>
        </div>
        <h1 class="title">Attachment Not Found</h1>
        <p class="message">
            {{ $message ?? 'The requested attachment link is invalid, deleted, or no longer available on our servers.' }}
        </p>
        <div class="notice-box">
            <i class="bi bi-info-circle me-1"></i>
            Please check that the full URL was copied correctly from the email, or contact the sender to request a replacement attachment.
        </div>
        <div class="text-muted" style="font-size: 12px;">
            HTTP Status: 404 &bull; Secure Outbound Email Client
        </div>
    </div>
</body>
</html>
