<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Set Your Password</title>
</head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;color:#111827;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f3f4f6;padding:24px 0;">
    <tr>
        <td align="center">
            <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="max-width:600px;width:100%;background:#ffffff;border:1px solid #e5e7eb;">
                <tr>
                    <td style="background:#111827;color:#ffffff;padding:20px 24px;">
                        <h1 style="margin:0;font-size:20px;line-height:28px;">Welcome to SimConnect</h1>
                    </td>
                </tr>

                <tr>
                    <td style="padding:24px;">
                        <p style="margin:0 0 14px;font-size:14px;line-height:22px;">Hello {{ $user->full_name }},</p>

                        <p style="margin:0 0 14px;font-size:14px;line-height:22px;">
                            Your account has been created. Please set your password using the secure link below.
                        </p>

                        <p style="margin:22px 0;">
                            <a href="{{ $setupUrl }}" style="display:inline-block;background:#111827;color:#ffffff;text-decoration:none;padding:12px 18px;font-size:14px;font-weight:bold;">
                                Set Password
                            </a>
                        </p>

                        <p style="margin:0 0 14px;font-size:13px;line-height:20px;color:#4b5563;">
                            This link expires on {{ $expiresAt->format('d M Y H:i') }}.
                        </p>

                        <p style="margin:0 0 14px;font-size:13px;line-height:20px;color:#4b5563;">
                            If the button does not work, copy and paste this link into your browser:
                        </p>

                        <p style="word-break:break-all;margin:0 0 14px;font-size:12px;line-height:18px;color:#2563eb;">
                            {{ $setupUrl }}
                        </p>

                        <p style="margin:24px 0 0;font-size:13px;line-height:20px;color:#4b5563;">
                            If you were not expecting this email, please ignore it or contact your administrator.
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
