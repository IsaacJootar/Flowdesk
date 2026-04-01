<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reset your Flowdesk password</title>
</head>
<body style="margin:0;padding:24px;background:#f4f7fb;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;margin:0 auto;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #dbe4f0;">
    <tr>
      <td style="padding:24px 32px;background:#0f2747;color:#ffffff;">
        <div style="font-size:22px;font-weight:700;letter-spacing:0.2px;">Flowdesk</div>
        <div style="font-size:14px;opacity:0.9;margin-top:6px;">Reset your password</div>
      </td>
    </tr>
    <tr>
      <td style="padding:32px;">
        <p style="margin:0 0 16px;font-size:16px;line-height:1.7;">
          Hello {{ $userName }},
        </p>

        <p style="margin:0 0 20px;font-size:15px;line-height:1.7;">
          We received a request to reset your Flowdesk password. Click the button below to continue.
        </p>

        <p style="margin:0 0 24px;">
          <a href="{{ $actionUrl }}" style="display:inline-block;background:#0f2747;color:#ffffff;text-decoration:none;padding:12px 18px;border-radius:8px;font-size:14px;font-weight:600;">
            Reset Password
          </a>
        </p>

        <p style="margin:0;font-size:13px;line-height:1.7;color:#64748b;">
          This link will expire in {{ $expiresInMinutes }} minutes. If you did not request a password reset, you can ignore this message.
        </p>
      </td>
    </tr>
  </table>
</body>
</html>
