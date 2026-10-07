<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $user = User::where('email', $this->email)->first();

        // 1. Check if user exists and password is valid
        if (! $user || ! Hash::check($this->password, $user->password)) {
            RateLimiter::hit($this->throttleKey());

            app(AuditLogService::class)->log(
                action: 'auth.failed',
                subject: null,
                changes: ['email' => $this->email, 'reason' => 'invalid_credentials']
            );

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        // 2. Check if user account is active
        if (! $user->is_active) {
            RateLimiter::hit($this->throttleKey());

            app(AuditLogService::class)->log(
                action: 'auth.rejected_inactive',
                subject: $user,
                changes: ['email' => $this->email, 'reason' => 'account_inactive'],
                userId: $user->id
            );

            throw ValidationException::withMessages([
                'email' => 'Akun Anda berstatus nonaktif. Silakan hubungi administrator.',
            ]);
        }

        // 3. Log the user in
        Auth::login($user, $this->boolean('remember'));

        $user->forceFill([
            'last_login_at' => now(),
        ])->save();

        RateLimiter::clear($this->throttleKey());

        app(AuditLogService::class)->log(
            action: 'auth.login',
            subject: $user,
            changes: ['ip' => $this->ip()],
            userId: $user->id
        );
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
