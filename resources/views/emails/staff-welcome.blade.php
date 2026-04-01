<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Welcome to Flowdesk</title>
</head>
<body style="margin:0;padding:24px;background:#f4f7fb;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;margin:0 auto;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #dbe4f0;">
    <tr>
      <td style="padding:24px 32px;background:#0f2747;color:#ffffff;">
        <div style="font-size:22px;font-weight:700;letter-spacing:0.2px;">Flowdesk</div>
        <div style="font-size:14px;opacity:0.9;margin-top:6px;">Welcome to your team account</div>
      </td>
    </tr>
    <tr>
      <td style="padding:32px;">
        <p style="margin:0 0 16px;font-size:16px;line-height:1.7;">
          Hello {{ $user->name }},
        </p>

        <p style="margin:0 0 16px;font-size:15px;line-height:1.7;">
          Your Flowdesk account has been created for
          <strong>{{ $companyName }}</strong> as
          <strong>{{ $user->role }}</strong>.
        </p>

        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:20px 0;background:#f6f9ff;border:1px solid #dbe4f0;border-radius:12px;">
          <tr>
            <td style="padding:20px;">
              <div style="font-size:14px;line-height:1.8;">
                <div><strong>Username:</strong> {{ $user->email }}</div>
                <div><strong>Temporary Password:</strong> {{ $temporaryPassword }}</div>
              </div>
            </td>
          </tr>
        </table>

        <p style="margin:0;font-size:13px;line-height:1.7;color:#64748b;">
          Please sign in and change your password immediately.
        </p>
      </td>
    </tr>
  </table>
</body>
</html>
