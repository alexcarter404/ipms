<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"></head>
<body style="font-family: Arial, Helvetica, sans-serif; color: #1f2937; font-size: 14px; line-height: 1.6;">
    <div style="border-bottom: 2px solid #4f46e5; padding-bottom: 10px; margin-bottom: 20px;">
        <strong style="color: #4f46e5; font-size: 16px;">IPMS — Daily Docket</strong><br>
        <span style="color: #6b7280; font-size: 11px;">{{ now()->format('l j F Y') }} · {{ $user->name }}</span>
    </div>

    <h3 style="font-size: 14px; margin-bottom: 6px;">Tasks due in the next {{ config('mailroom.digest.task_days') }} days</h3>
    @forelse ($tasks as $task)
        <p style="margin: 3px 0;">
            <strong>{{ $task->due_date->format('d M') }}</strong>
            @if ($task->due_date->isPast()) <span style="color: #dc2626; font-weight: bold;">OVERDUE</span> @endif
            — {{ $task->title }}
            <span style="color: #6b7280;">({{ $task->matter->reference }})</span>
        </p>
    @empty
        <p style="color: #6b7280;">Nothing due — enjoy the quiet.</p>
    @endforelse

    <h3 style="font-size: 14px; margin: 18px 0 6px;">Renewals due in the next {{ config('mailroom.digest.renewal_days') }} days</h3>
    @forelse ($renewals as $renewal)
        <p style="margin: 3px 0;">
            <strong>{{ $renewal->due_date->format('d M') }}</strong>
            — {{ $renewal->matter->reference }} year {{ $renewal->cycle }}
            <span style="color: #6b7280;">({{ $renewal->matter->country_code }})</span>
        </p>
    @empty
        <p style="color: #6b7280;">No renewals in the window.</p>
    @endforelse

    <p style="margin-top: 28px; border-top: 1px solid #e5e7eb; padding-top: 10px; color: #9ca3af; font-size: 11px;">
        Sent automatically by IPMS. Manage matters at {{ config('app.url') }}.
    </p>
</body>
</html>
