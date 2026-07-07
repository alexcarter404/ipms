<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"></head>
<body style="font-family: Arial, Helvetica, sans-serif; color: #1f2937; font-size: 14px; line-height: 1.6;">
    <div style="border-bottom: 2px solid #4f46e5; padding-bottom: 10px; margin-bottom: 20px;">
        <strong style="color: #4f46e5; font-size: 16px;">IPMS — Scheduled Report</strong>
    </div>

    <p>
        Your {{ $report->schedule }} report <strong>{{ $report->name }}</strong> is attached
        as CSV — {{ $rowCount }} row(s) as of {{ now()->format('j F Y H:i') }}.
    </p>

    <p style="margin-top: 24px; border-top: 1px solid #e5e7eb; padding-top: 10px; color: #9ca3af; font-size: 11px;">
        Manage your reports at {{ config('app.url') }}/reports.
    </p>
</body>
</html>
