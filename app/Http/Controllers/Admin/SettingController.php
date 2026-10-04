<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSettingsRequest;
use App\Models\Setting;
use App\Services\AuditLogService;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Storage;

class SettingController extends Controller
{
    public function edit(SettingsService $settingsService)
    {
        $setting = $settingsService->current();

        return view('admin.settings.edit', compact('setting'));
    }

    public function update(UpdateSettingsRequest $request, SettingsService $settingsService, AuditLogService $auditLog)
    {
        $setting = Setting::query()->orderBy('id')->first() ?? new Setting;
        $before = $setting->getAttributes();
        $wasNew = ! $setting->exists;
        $data = collect($request->validated())->except(['bank_qr', 'remove_bank_qr'])->all();

        if ($request->hasFile('bank_qr') || $request->boolean('remove_bank_qr')) {
            if ($setting->bank_qr_path) {
                Storage::disk('public')->delete($setting->bank_qr_path);
            }

            $data['bank_qr_path'] = $request->hasFile('bank_qr')
                ? $request->file('bank_qr')->store('settings', 'public')
                : null;
        }

        $setting->fill($data)->save();

        // Bank details changes are included: guests send money to that account.
        if ($wasNew) {
            $auditLog->log('settings.created', 'settings', $setting->id, 'Saved hotel settings for the first time.');
        } else {
            $auditLog->logChanges('settings.updated', 'settings', $setting, 'Updated hotel settings.', $before);
        }

        $settingsService->forget();

        return redirect()->route('admin.settings.edit')->with('success', 'Settings updated. Changes are now live on the public site.');
    }
}
