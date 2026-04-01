<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Flowdesk Vendor Reminder</title>
</head>
<body style="margin:0;padding:24px;background:#f4f7fb;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;margin:0 auto;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #dbe4f0;">
    <tr>
      <td style="padding:24px 32px;background:#0f2747;color:#ffffff;">
        <div style="font-size:22px;font-weight:700;letter-spacing:0.2px;">Flowdesk</div>
        <div style="font-size:14px;opacity:0.9;margin-top:6px;">Vendor invoice update</div>
      </td>
    </tr>
    <tr>
      <td style="padding:32px;">
        <p style="margin:0 0 16px;font-size:16px;line-height:1.7;">
          Hello {{ (int) ($log->recipient_user_id ?? 0) > 0 ? ($log->recipient?->name ?? 'Finance Team') : ($log->vendor?->name ?? 'Vendor') }},
        </p>

        <p style="margin:0 0 16px;font-size:15px;line-height:1.7;">
          This is a Flowdesk reminder for an invoice update.
        </p>

        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:20px 0;background:#f6f9ff;border:1px solid #dbe4f0;border-radius:12px;">
          <tr>
            <td style="padding:20px;">
              <div style="font-size:14px;line-height:1.8;">
                <div><strong>Vendor:</strong> {{ $log->vendor?->name ?? 'Vendor' }}</div>
                <div><strong>Invoice:</strong> {{ $log->invoice?->invoice_number ?? 'N/A' }}</div>
                <div><strong>Due Date:</strong> {{ optional($log->invoice?->due_date)->format('Y-m-d') ?? 'N/A' }}</div>
                <div><strong>Outstanding:</strong> {{ strtoupper((string) ($log->invoice?->currency ?? 'NGN')) }} {{ number_format((int) ($log->invoice?->outstanding_amount ?? 0), 2) }}</div>
                <div><strong>Event:</strong> {{ \Illuminate\Support\Str::of((string) $log->event)->replace('.', ' ')->headline() }}</div>
              </div>
            </td>
          </tr>
        </table>

        <p style="margin:0;font-size:13px;line-height:1.7;color:#64748b;">
          Please review the invoice in Flowdesk to take action.
        </p>
      </td>
    </tr>
  </table>
</body>
</html>
