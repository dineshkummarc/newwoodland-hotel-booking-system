<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSettingsRequest;
use App\Models\Setting;
use App\Services\SettingsService;

class SettingController extends Controller
{
    public function edit(SettingsService $settingsService)
    {
        $setting = $settingsService->current();

        return view('admin.settings.edit', compact('setting'));
    }

    public function update(UpdateSettingsRequest $request, SettingsService $settingsService)
    {
        $setting = Setting::query()->orderBy('id')->first() ?? new Setting;
        $setting->fill($request->validated())->save();

        $settingsService->forget();

        return redirect()->route('admin.settings.edit')->with('success', 'Settings updated. Changes are now live on the public site.');
    }
}
