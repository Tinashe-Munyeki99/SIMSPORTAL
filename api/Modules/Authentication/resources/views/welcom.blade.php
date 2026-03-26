<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Welcome to FiscTrack</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f4f4;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#f4f4f4; padding:40px 0;">
    <tr>
        <td align="center">
            <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="background-color:#ffffff; border-radius:8px; overflow:hidden; box-shadow:0 4px 10px rgba(0,0,0,0.1); font-family:'Segoe UI', Arial, sans-serif;">
                <tr>
                    <td align="center" style="background-color:#4b3ca8; padding:20px;">
                        <h2 style="color:#ffffff; margin:0; font-size:22px;">Welcome to SIMS</h2>
                    </td>
                </tr>
                <tr>
                    <td style="padding:30px; color:#333333; font-size:15px; line-height:1.6;">
                        <p>Dear <strong>{{ $user->full_name }}</strong>,</p>

                        <p>Your account has been successfully created on <strong>SIMS</strong>. Below are your login details:</p>

                        <table width="100%" cellspacing="0" cellpadding="8" border="0" style="background-color:#f9f9f9; border:1px solid #ddd; border-radius:5px; margin:15px 0;">
                            <tr>
                                <td width="30%" style="font-weight:bold;">Email:</td>
                                <td>{{ $user->email }}</td>
                            </tr>
                            <tr>
                                <td width="30%" style="font-weight:bold;">Password:</td>
                                <td>{{ $password }}</td>
                            </tr>
                        </table>

                        <p>Please log in and change your password immediately for security reasons.</p>

                        <p style="text-align:center; margin:30px 0;">
                            <a href="{{ env('FRONTEND_LOGIN_URL', 'http://localhost:3000/portal/auth/login') }}"
                               style="background-color:#4b3ca8; color:#ffffff; text-decoration:none; padding:12px 25px; border-radius:5px; display:inline-block; font-weight:bold;">
                                Login to SIMS
                            </a>
                        </p>

                        <p style="margin-top:25px; font-size:14px; color:#777777;">
                            Kind regards,<br>
                            <strong>SIMS</strong>
                        </p>
                    </td>
                </tr>
                <tr>
                    <td align="center" style="background-color:#f4f4f4; padding:15px; font-size:12px; color:#888;">
                        &copy; {{ date('Y') }} SIMS. All rights reserved.
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
