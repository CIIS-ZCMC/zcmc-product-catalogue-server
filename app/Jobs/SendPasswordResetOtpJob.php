<?php

namespace App\Jobs;

use App\Models\User;
use App\Mail\PasswordResetOtpMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendPasswordResetOtpJob implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public array $backoff = [5, 15, 30];

    /**
     * Create a new job instance.
     */
    public function __construct(public User $user, public string $otp)
    {
        $this->onQueue('email');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Mail::to($this->user->email)->send(new PasswordResetOtpMail($this->user, $this->otp));
            Log::info("Password reset OTP email sent successfully to {$this->user->email}");
        } catch (\Throwable $e) {
            Log::error("Failed to send password reset OTP to {$this->user->email}: " . $e->getMessage());
            throw $e;
        }
    }
}
