<x-mail::message>
# Reset Password

You are receiving this email because we received a password reset request for your account.

Your reset code is: <b>{{ $token }}</b>

If you did not request a password reset, no further action is required.

Regards,<br>
{{ config('app.name') }}
</x-mail::message>
