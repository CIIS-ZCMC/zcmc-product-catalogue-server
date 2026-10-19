<div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; max-width: 580px; margin: 0 auto; padding: 24px; border: 1px solid #e2e8f0; border-radius: 12px; background-color: #ffffff;">
    <div style="background: linear-gradient(135deg, #0f766e 0%, #0d9488 100%); padding: 24px; border-radius: 8px; text-align: center; color: #ffffff;">
        <div style="font-size: 13px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; opacity: 0.9;">Republic of the Philippines &bull; DOH</div>
        <h2 style="margin: 6px 0 0 0; font-size: 20px; font-weight: 800;">Zamboanga City Medical Center</h2>
        <div style="font-size: 13px; opacity: 0.85; margin-top: 4px;">Product Catalogue Portal</div>
    </div>
    <div style="padding: 24px 8px 8px 8px; color: #334155; line-height: 1.6;">
        <h3 style="color: #0f172a; margin-top: 0;">Welcome, {{ $user->name ?: ($user->first_name . ' ' . $user->last_name) }}!</h3>
        <p>Your account for <strong>{{ $user->email }}</strong> has been successfully registered to access the ZCMC Product Catalogue Portal.</p>

        @if(!empty($initialPassword))
        <div style="background-color: #f8fafc; border: 1px solid #cbd5e1; border-radius: 8px; padding: 18px; margin: 20px 0;">
            <div style="font-size: 12px; font-weight: 700; color: #0f766e; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 10px;">Your Initial Login Credentials:</div>
            <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
                <tr>
                    <td style="padding: 5px 0; color: #64748b; width: 140px;">Email / Username:</td>
                    <td style="padding: 5px 0; font-weight: 600; color: #0f172a;">{{ $user->email }}</td>
                </tr>
                <tr>
                    <td style="padding: 5px 0; color: #64748b;">Initial Password:</td>
                    <td style="padding: 5px 0; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size: 15px; font-weight: 700; color: #0f766e; letter-spacing: 0.5px;">{{ $initialPassword }}</td>
                </tr>
            </table>
            <div style="margin-top: 14px; padding: 10px 14px; background-color: #f0fdf4; border-left: 3px solid #16a34a; border-radius: 4px; font-size: 12px; color: #166534; line-height: 1.5;">
                <strong>First-Time Sign-In:</strong> Upon your first login using this initial password, the system will prompt you to set your own personalized permanent password before accessing the portal.
            </div>
        </div>
        @endif

        <div style="background-color: #f8fafc; border-left: 4px solid #0f766e; padding: 12px 16px; margin: 20px 0; border-radius: 4px;">
            <div style="font-size: 13px; font-weight: 700; color: #0f766e; text-transform: uppercase;">Portal Privileges:</div>
            <ul style="margin: 8px 0 0 0; padding-left: 20px; font-size: 14px; color: #475569;">
                <li>Explore UNSPSC Standard Classification & Categories</li>
                <li>Search Institutional & Internal Categories</li>
                <li>Standardized Hospital Item Descriptions</li>
                <li>Track Unit Price Histories & Technical PDF Specs</li>
            </ul>
        </div>
        <p style="font-size: 14px; color: #64748b;">If you did not authorize this registration, please immediately inform the Integrated Management Information System Section (IMISS).</p>
    </div>
    <div style="margin-top: 24px; padding-top: 16px; border-top: 1px solid #e2e8f0; text-align: center; font-size: 12px; color: #94a3b8;">
        &copy; {{ date('Y') }} Zamboanga City Medical Center. All rights reserved.
    </div>
</div>
