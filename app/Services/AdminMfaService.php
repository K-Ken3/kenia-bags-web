<?php

namespace App\Services;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

class AdminMfaService
{
    public function __construct(private Google2FA $google2fa) {}

    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey();
    }

    public function otpauthUrl(User $user, string $secret): string
    {
        return $this->google2fa->getQRCodeUrl(
            (string) config('app.name', 'Store'),
            $user->email,
            $secret,
        );
    }

    public function qrCodeDataUri(string $otpauthUrl): string
    {
        $renderer = new ImageRenderer(new RendererStyle(240, 2), new SvgImageBackEnd);
        $svg = (new Writer($renderer))->writeString($otpauthUrl);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    /**
     * Returns the accepted TOTP timestep, or null when the code is invalid or
     * has already been used. Codes within one 30-second step of the server clock
     * are accepted to tolerate small clock drift.
     */
    public function verifyCode(User $user, string $code): ?int
    {
        $secret = $user->two_factor_secret;
        $code = preg_replace('/\D/', '', $code) ?? '';

        if (! is_string($secret) || $secret === '' || strlen($code) !== 6) {
            return null;
        }

        try {
            $timestep = $this->google2fa->verifyKeyNewer(
                $secret,
                $code,
                (int) ($user->two_factor_last_used_timestep ?? 0),
                1,
            );
        } catch (\Throwable) {
            return null;
        }

        return is_int($timestep) ? $timestep : null;
    }

    public function currentCode(string $secret): string
    {
        return str_pad((string) $this->google2fa->getCurrentOtp($secret), 6, '0', STR_PAD_LEFT);
    }

    /**
     * @return list<string>
     */
    public function generateRecoveryCodes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = Str::upper(Str::random(5)).'-'.Str::upper(Str::random(5));
        }

        return $codes;
    }

    /**
     * @param  list<string>  $codes
     * @return list<string>
     */
    public function hashRecoveryCodes(array $codes): array
    {
        return array_map(
            fn (string $code) => Hash::make($this->normalizeRecoveryCode($code)),
            $codes,
        );
    }

    public function consumeRecoveryCode(User $user, string $code): bool
    {
        $normalized = $this->normalizeRecoveryCode($code);
        if (strlen($normalized) < 8) {
            return false;
        }

        $hashes = $user->two_factor_recovery_codes ?? [];
        foreach ($hashes as $index => $hash) {
            if (is_string($hash) && Hash::check($normalized, $hash)) {
                unset($hashes[$index]);
                $user->forceFill(['two_factor_recovery_codes' => array_values($hashes)])->save();

                return true;
            }
        }

        return false;
    }

    public function normalizeRecoveryCode(string $code): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? '');
    }
}
