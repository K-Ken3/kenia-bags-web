<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSettingsRequest;
use App\Models\AuditLog;
use App\Models\Setting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SettingController extends Controller
{
    private const EDITABLE = [
        'brand_name', 'brand_logo', 'contact_email', 'contact_phone', 'whatsapp_number',
        'delivery_fee_rwf', 'delivery_fee_confirmed', 'manual_momo_enabled',
        'delivery_information', 'momo_pay_code', 'unpaid_order_expiry_minutes',
    ];

    public function index()
    {
        $settings = Setting::whereIn('key', self::EDITABLE)->pluck('value', 'key');

        return inertia('Admin/Settings', [
            'settings' => $settings,
            'brandLogoUrl' => $settings['brand_logo']
                ? Storage::disk(config('store.catalog_disk', 'public'))->url($settings['brand_logo'])
                : asset('images/isimbi-mart-logo.png'),
            'paymentMode' => app()->environment(['local', 'testing']) && config('payments.sandbox_enabled')
                ? 'sandbox enabled for development only'
                : 'No live provider configured',
        ]);
    }

    public function update(UpdateSettingsRequest $request)
    {
        $settings = $request->validated();
        $logo = $settings['brand_logo'] ?? null;
        unset($settings['brand_logo']);
        $settings['delivery_fee_confirmed'] = isset($settings['delivery_fee_confirmed']) ? '1' : '0';
        $settings['manual_momo_enabled'] = isset($settings['manual_momo_enabled']) ? '1' : '0';
        if ($settings['manual_momo_enabled'] === '1' && empty($settings['momo_pay_code'])) {
            return back()->withErrors(['momo_pay_code' => 'Enter the confirmed pay code before enabling manual MoMo.']);
        }

        $newLogoPath = $logo ? $this->storeLogo($logo) : null;
        $oldLogoPath = Setting::get('brand_logo');

        DB::transaction(function () use ($settings, $newLogoPath) {
            foreach ($settings as $key => $value) {
                Setting::updateOrCreate(['key' => $key], ['value' => (string) $value]);
            }
            if ($newLogoPath) {
                Setting::updateOrCreate(['key' => 'brand_logo'], ['value' => $newLogoPath]);
            }
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'settings.updated',
                'metadata' => ['keys' => array_keys($settings)],
            ]);
        });
        if ($newLogoPath && $oldLogoPath && $oldLogoPath !== $newLogoPath) {
            Storage::disk(config('store.catalog_disk', 'public'))->delete($oldLogoPath);
        }

        return back()->with('success', 'Store settings saved.');
    }

    private function storeLogo(UploadedFile $file): string
    {
        $dimensions = getimagesize($file->getPathname());
        if (! $dimensions || $dimensions[0] > 4000 || $dimensions[1] > 4000) {
            throw ValidationException::withMessages(['brand_logo' => 'Choose an image no larger than 4000 by 4000 pixels.']);
        }
        $source = imagecreatefromstring($file->getContent());
        if (! $source) {
            throw ValidationException::withMessages(['brand_logo' => 'The uploaded logo could not be decoded.']);
        }
        $scale = min(1, 1200 / max(imagesx($source), imagesy($source)));
        $image = imagecreatetruecolor((int) (imagesx($source) * $scale), (int) (imagesy($source) * $scale));
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagecopyresampled($image, $source, 0, 0, 0, 0, imagesx($image), imagesy($image), imagesx($source), imagesy($source));
        ob_start();
        $encoded = imagewebp($image, null, 82);
        $webp = ob_get_clean();
        imagedestroy($source);
        imagedestroy($image);
        if (! $encoded || ! is_string($webp) || $webp === '') {
            throw ValidationException::withMessages(['brand_logo' => 'The uploaded logo could not be converted.']);
        }

        $path = 'brand/'.Str::uuid().'.webp';
        if (! Storage::disk(config('store.catalog_disk', 'public'))->put($path, $webp, ['visibility' => 'public'])) {
            abort(500, 'The brand logo could not be saved.');
        }

        return $path;
    }
}
