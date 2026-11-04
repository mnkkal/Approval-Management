<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Exception;

class OtpVerificationService
{
    protected int $expiryMinutes;
    protected int $maxAttempts;
    protected int $resendThrottleSeconds;

    public function __construct()
    {
        $this->expiryMinutes = (int) env('OTP_EXPIRY_MINUTES', 15);
        $this->maxAttempts = (int) env('OTP_MAX_ATTEMPTS', 5);
        $this->resendThrottleSeconds = (int) env('OTP_RESEND_THROTTLE_SECONDS', 60);
    }

    /**
     * Generate and dispatch a 6-digit numeric OTP via Hostinger SMTP.
     */
    public function sendChallenge(string $email, string $invitationId, string $workspaceId, string $ipAddress): array
    {
        $ipHash = hash('sha256', $ipAddress);

        // Check resend throttle
        $recentOtp = DB::table('email_otps')
            ->where('email', $email)
            ->where('created_at', '>=', Carbon::now()->subSeconds($this->resendThrottleSeconds))
            ->first();

        if ($recentOtp) {
            $retryAfter = $this->resendThrottleSeconds - Carbon::parse($recentOtp->created_at)->diffInSeconds(Carbon::now());
            throw new Exception("Please wait {$retryAfter} seconds before requesting a new verification code.");
        }

        // Generate cryptographically secure 6-digit integer (100000 - 999999)
        $rawOtp = (string) random_int(100000, 999999);
        $otpHash = hash('sha256', $rawOtp);
        $expiresAt = Carbon::now()->addMinutes($this->expiryMinutes);

        // Invalidate older unused OTPs for this email
        DB::table('email_otps')
            ->where('email', $email)
            ->whereNull('verified_at')
            ->delete();

        // Store hashed OTP
        $otpId = Str::uuid()->toString();
        DB::table('email_otps')->insert([
            'id' => $otpId,
            'workspace_id' => $workspaceId,
            'email' => $email,
            'invitation_id' => $invitationId,
            'otp_hash' => $otpHash,
            'attempts_count' => 0,
            'max_attempts' => $this->maxAttempts,
            'expires_at' => $expiresAt,
            'ip_address_hash' => $ipHash,
            'created_at' => Carbon::now(),
        ]);

        // Send email via Hostinger SMTP
        $fromEmail = config('mail.from.address', 'contact@curvemetrics.in');
        $fromName = config('mail.from.name', 'Curve Metrics Approval Portal');

        Mail::send([], [], function ($message) use ($email, $rawOtp, $fromEmail, $fromName) {
            $message->to($email)
                ->from($fromEmail, $fromName)
                ->subject("Your Review Verification Code: {$rawOtp}")
                ->html("
                    <div style='font-family: -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Helvetica, Arial, sans-serif; max-width: 520px; margin: 0 auto; padding: 32px 24px; border: 1px solid #E2E8F0; border-radius: 8px;'>
                        <h2 style='color: #0F172A; margin-top: 0;'>Review Access Verification</h2>
                        <p style='color: #475569; font-size: 15px; line-height: 1.5;'>
                            You have been invited to review and approve creative deliverables. Enter the verification code below to access your session:
                        </p>
                        <div style='background-color: #F8FAFC; border: 1px solid #CBD5E1; border-radius: 6px; padding: 18px; text-align: center; margin: 24px 0;'>
                            <span style='font-size: 32px; font-weight: 700; letter-spacing: 6px; color: #2563EB;'>{$rawOtp}</span>
                        </div>
                        <p style='color: #64748B; font-size: 13px; line-height: 1.5;'>
                            This code will expire in {$this->expiryMinutes} minutes. If you did not request this access, you can safely ignore this email.
                        </p>
                        <hr style='border: none; border-top: 1px solid #E2E8F0; margin: 24px 0;' />
                        <p style='color: #94A3B8; font-size: 12px; margin-bottom: 0;'>
                            ApexApprove · Secure Client Review Management
                        </p>
                    </div>
                ");
        });

        // Record audit event
        DB::table('audit_logs')->insert([
            'workspace_id' => $workspaceId,
            'event_type' => 'otp.sent',
            'actor_email' => $email,
            'ip_address_hash' => $ipHash,
            'entity_type' => 'email_otp',
            'entity_id' => $otpId,
            'payload' => json_encode(['expires_at' => $expiresAt->toIso8601String()]),
            'created_at' => Carbon::now(),
        ]);

        return [
            'status' => 'sent',
            'email' => $email,
            'expires_in_minutes' => $this->expiryMinutes,
        ];
    }

    /**
     * Verify the supplied OTP and generate an active guest session token.
     */
    public function verifyChallenge(string $email, string $invitationId, string $submittedOtp, string $ipAddress, string $userAgent): array
    {
        $ipHash = hash('sha256', $ipAddress);

        $otpRecord = DB::table('email_otps')
            ->where('email', $email)
            ->where('invitation_id', $invitationId)
            ->whereNull('verified_at')
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$otpRecord) {
            throw new Exception("No active verification code found for this review request. Please request a new code.");
        }

        // Check if expired
        if (Carbon::now()->greaterThan(Carbon::parse($otpRecord->expires_at))) {
            throw new Exception("Verification code has expired. Please request a new one.");
        }

        // Check max attempt lockout
        if ($otpRecord->attempts_count >= $otpRecord->max_attempts) {
            throw new Exception("Maximum verification attempts exceeded. For security, please request a new code.");
        }

        // Validate hash
        $submittedHash = hash('sha256', trim($submittedOtp));
        if (!hash_equals($otpRecord->otp_hash, $submittedHash)) {
            // Increment failed attempt counter
            DB::table('email_otps')
                ->where('id', $otpRecord->id)
                ->increment('attempts_count');

            $remaining = $otpRecord->max_attempts - ($otpRecord->attempts_count + 1);
            throw new Exception("Invalid verification code. {$remaining} attempts remaining.");
        }

        // Mark OTP as verified
        DB::table('email_otps')
            ->where('id', $otpRecord->id)
            ->update(['verified_at' => Carbon::now()]);

        // Generate Guest Session Token
        $rawSessionToken = 'gses_' . Str::random(48);
        $sessionTokenHash = hash('sha256', $rawSessionToken);
        $sessionExpiresAt = Carbon::now()->addDays(7);

        DB::table('guest_sessions')->insert([
            'id' => Str::uuid()->toString(),
            'invitation_id' => $invitationId,
            'session_token_hash' => $sessionTokenHash,
            'ip_address_hash' => $ipHash,
            'user_agent' => substr($userAgent, 0, 512),
            'expires_at' => $sessionExpiresAt,
            'created_at' => Carbon::now(),
        ]);

        // Update invitation last accessed timestamp
        DB::table('guest_review_invitations')
            ->where('id', $invitationId)
            ->update(['last_accessed_at' => Carbon::now()]);

        // Log audit event
        DB::table('audit_logs')->insert([
            'workspace_id' => $otpRecord->workspace_id,
            'event_type' => 'otp.verified',
            'actor_email' => $email,
            'ip_address_hash' => $ipHash,
            'entity_type' => 'guest_session',
            'entity_id' => $otpRecord->id,
            'payload' => json_encode(['expires_at' => $sessionExpiresAt->toIso8601String()]),
            'created_at' => Carbon::now(),
        ]);

        return [
            'status' => 'verified',
            'session_token' => $rawSessionToken,
            'expires_at' => $sessionExpiresAt->toIso8601String(),
        ];
    }
}
