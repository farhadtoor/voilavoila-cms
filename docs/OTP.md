# VoilaVoila Authenticator OTP 1.0

This plugin adds a **TOTP second factor** to owner sign-in: password, then authenticator code. It requires the **CMS 1.3.1 integration patch**. A plugin alone on unpatched 1.3.0 cannot protect every private entry point, so its manifest refuses activation there.

## Enable

1. Back up application code and **complete private storage**. Apply the supplied 1.3.0 → 1.3.1 update, or use the complete 1.3.1 package for a new installation.
2. If not already supplied, install `VoilaVoila-OTP-1.0.0.zip` under Plugins. Activate it and open **Authenticator OTP**.
3. Enter your current password and start setup.
4. Add a TOTP account in an authenticator app such as Aegis, 2FAS, Google Authenticator or Microsoft Authenticator. Scan the QR or enter the manual key. QR generation stays **local in the browser**, without a third-party QR service.
5. Confirm the current six-digit code. Enforcement starts only after a valid proof; pending setup expires after ten minutes.
6. Store the **ten recovery codes**, shown once, offline and separately from the phone. They are not emailed. Reloading hides the list.
7. Test another browser: password-only access must stop at the OTP challenge, including for private previews/media.

Recovery codes replace one OTP check after the password. Accepted TOTPs cannot be reused. The deployment profile is SHA-1, six digits, 30-second steps and ± one-step clock tolerance. Synchronize server time using NTP and enable automatic phone time.

## Management and recovery

The panel shows remaining recovery codes. Replacing them or disabling OTP requires **the current password and a valid TOTP/recovery code**. Replacement invalidates old recovery codes and other sessions. Wait for the next TOTP if the current one was already consumed.

Native password recovery **does not bypass** the factor. Safe mode does not bypass it either. Plugin deactivation/deletion is refused while configured. Missing plugin files fail closed.

If both phone and recovery codes are lost, an operator with local server access can run, using the site's `VV_STORAGE_PATH`:

```sh
php content/plugins/otp/bin/otp.php status
php content/plugins/otp/bin/otp.php disable --confirm=DISABLE-OTP
```

Local disable revokes owner and WordPress handoff sessions. It is an SSH operation, not a public URL. Keep server access or a tested full-restore procedure before enrollment.

## Storage and scope

The confirmed secret is encrypted using AES-256-GCM in private owner state. Its separate key is stored in guarded, private `VV_STORAGE_PATH/otp/key.php`. Recovery codes are keyed HMACs, never cleartext. Pending provisioning data is encrypted in the owner session. OpenSSL AES-256-GCM is required.

Native editorial backups contain **neither secret, recovery hashes nor key**. Full backups must retain the owner SQLite database and all private storage, including the OTP key. Editorial restores retain the current owner's factor. Protect complete backups like credentials.

The 1.3.1 hook runs during session establishment/validation, including private controllers that do not boot plugins. Enrollment/rotation revokes old sessions through the owner epoch. WordPress handoff follows this revocation; stale privileged browser cookies cannot keep administrative REST access. Independent service API keys retain their own authentication and lifecycle.

TOTP adds a login factor; it does not neutralize session theft or malicious trusted PHP. Verified sessions retain native CMS expirations. Install only trusted server modules.

The supplied report separates RFC 6238 SHA-1/256/512 vectors, HTTP checks, actual QR decoding and browser journeys. It does not automate installation of the named mobile apps.

[RFC 6238](https://www.rfc-editor.org/rfc/rfc6238) · [otpauth format](https://github.com/google/google-authenticator/wiki/Key-Uri-Format) · [Nayuki QR library, MIT](https://www.nayuki.io/page/qr-code-generator-library)

[Français](OTP.fr.md) · [Both extensions](../EXTENSIONS-ENGINE-OTP-FR-EN.md)
