<?php

namespace App\Http\Controllers;

use App\Models\BusinessSetting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BusinessSettingsController extends Controller
{
    /**
     * @return array<string, string>
     */
    private function settingKeys(): array
    {
        return [
            'name' => 'Business Name',
            'tagline' => 'Tagline',
            'address' => 'Address',
            'phone' => 'Phone',
            'email' => 'Email',
            'tin' => 'TIN',
            'currency' => 'Currency',
            'receipt_footer' => 'Receipt Footer',
            'invoice_footer' => 'Invoice Footer',
            'statement_footer' => 'Statement Footer',
            'logo_url' => 'Logo URL',
            'admin_approval_pin' => 'Admin Approval PIN',
            'cashier_discount_limit' => 'Cashier Discount Limit',
        ];
    }

    public function edit(): View
    {
        $settings = BusinessSetting::query()
            ->whereIn('key', array_keys($this->settingKeys()))
            ->pluck('value', 'key')
            ->all();

        return view('settings.business', [
            'settings' => array_merge([
                'name' => config('business.name', 'Apples Of Gold'),
                'tagline' => config('business.tagline', 'Business Management System'),
                'address' => config('business.address', ''),
                'phone' => config('business.phone', ''),
                'email' => config('business.email', ''),
                'tin' => config('business.tin', ''),
                'currency' => config('business.currency', 'UGX'),
                'receipt_footer' => config('business.receipt_footer', ''),
                'invoice_footer' => config('business.invoice_footer', ''),
                'statement_footer' => config('business.statement_footer', ''),
                'logo_url' => config('business.logo_url', ''),
                'admin_approval_pin' => config('business.admin_approval_pin', ''),
                'cashier_discount_limit' => config('business.cashier_discount_limit', 0),
            ], $settings),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'tin' => ['nullable', 'string', 'max:100'],
            'currency' => ['required', 'string', 'max:20'],
            'receipt_footer' => ['nullable', 'string', 'max:1000'],
            'invoice_footer' => ['nullable', 'string', 'max:1000'],
            'statement_footer' => ['nullable', 'string', 'max:1000'],
            'admin_approval_pin' => ['nullable', 'string', 'max:50'],
            'cashier_discount_limit' => ['nullable', 'numeric', 'min:0'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'clear_logo' => ['nullable', 'boolean'],
        ]);

        $currentLogoUrl = BusinessSetting::query()->where('key', 'logo_url')->value('value') ?? config('business.logo_url', '');
        $logoUrl = $currentLogoUrl;

        if ($request->boolean('clear_logo')) {
            $this->deleteStoredLogo($currentLogoUrl);
            $logoUrl = '';
        }

        if ($request->hasFile('logo')) {
            $this->deleteStoredLogo($currentLogoUrl);
            $path = $request->file('logo')->store('business', 'public');
            $logoUrl = Storage::url($path);
        }

        $toSave = [
            'name' => $validated['name'],
            'tagline' => $validated['tagline'] ?? '',
            'address' => $validated['address'] ?? '',
            'phone' => $validated['phone'] ?? '',
            'email' => $validated['email'] ?? '',
            'tin' => $validated['tin'] ?? '',
            'currency' => $validated['currency'],
            'receipt_footer' => $validated['receipt_footer'] ?? '',
            'invoice_footer' => $validated['invoice_footer'] ?? '',
            'statement_footer' => $validated['statement_footer'] ?? '',
            'logo_url' => $logoUrl,
            'admin_approval_pin' => $validated['admin_approval_pin'] ?? '',
            'cashier_discount_limit' => (string) round((float) ($validated['cashier_discount_limit'] ?? 0), 2),
        ];

        foreach ($toSave as $key => $value) {
            BusinessSetting::query()->updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }

        return redirect()
            ->route('settings.business.edit')
            ->with('status', 'Business settings updated successfully.');
    }

    private function deleteStoredLogo(?string $logoUrl): void
    {
        if (! $logoUrl || ! str_starts_with($logoUrl, '/storage/business/')) {
            return;
        }

        $path = ltrim(str_replace('/storage/', '', $logoUrl), '/');

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
