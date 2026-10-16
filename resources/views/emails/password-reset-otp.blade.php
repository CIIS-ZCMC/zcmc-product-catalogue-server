<div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; max-width: 580px; margin: 0 auto; padding: 24px; border: 1px solid #e2e8f0; border-radius: 12px; background-color: #ffffff;">
    <div style="background-color: #0f766e; padding: 20px; border-radius: 8px; text-align: center; color: #ffffff;">
        <h2 style="margin: 0; font-size: 18px;">Zamboanga City Medical Center</h2>
        <p style="margin: 4px 0 0; font-size: 13px; opacity: 0.9;">Catalogue Portal &bull; Password Reset Verification</p>
    </div>
    <div style="padding: 24px 8px; color: #334155; line-height: 1.6;">
        <p>Hello <strong>{{ $user->name ?: ($user->first_name . ' ' . $user->last_name) }}</strong>,</p>
        <p>We received a request to reset your password for the ZCMC Product Catalogue Portal. Use the 6-digit verification code below to complete your reset:</p>
        <div style="text-align: center; margin: 28px 0;">
            <span style="display: inline-block; font-size: 32px; font-weight: 800; letter-spacing: 0.25em; color: #0f766e; background-color: #f0fdfa; padding: 14px 28px; border-radius: 8px; border: 2px dashed #0d9488;">
                {{ $otp }}
            </span>
        </div>
        <p style="font-size: 13px; color: #64748b; text-align: center;">This code will expire in <strong>15 minutes</strong>.</p>
        <hr style="border: 0; border-top: 1px solid #e2e8f0; margin: 24px 0;">
        <p style="font-size: 12px; color: #94a3b8;">If you did not request a password reset, you can safely ignore this email; your password will remain unchanged.</p>
    </div>
</div>
