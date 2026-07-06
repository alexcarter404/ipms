<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"></head>
<body style="font-family: Arial, Helvetica, sans-serif; color: #1f2937; font-size: 14px; line-height: 1.6;">
    <div style="border-bottom: 2px solid #4f46e5; padding-bottom: 10px; margin-bottom: 20px;">
        <strong style="color: #4f46e5; font-size: 16px;">IPMS Partners LLP</strong><br>
        <span style="color: #6b7280; font-size: 11px;">Intellectual Property Attorneys</span>
    </div>

    @if ($communication->matter)
        <p style="color: #6b7280; font-size: 12px;">
            Our ref: <strong style="color: #1f2937;">{{ $communication->matter->reference }}</strong>
            — {{ $communication->matter->title }}
        </p>
    @endif

    <div style="white-space: pre-wrap;">{{ $communication->body }}</div>

    <p style="margin-top: 28px; border-top: 1px solid #e5e7eb; padding-top: 10px; color: #9ca3af; font-size: 11px;">
        This message was sent by IPMS on behalf of the responsible attorney.
    </p>
</body>
</html>
