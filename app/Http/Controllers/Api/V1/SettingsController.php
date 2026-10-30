<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Setting;
use Illuminate\Support\Facades\Storage;

class SettingsController extends Controller
{
    public function index()
    {
        $settings = Setting::pluck('value', 'key');
        return response()->json([
            'success' => true,
            'data' => $settings
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->except('school_logo');

        foreach ($data as $key => $value) {
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }

        if ($request->hasFile('school_logo')) {
            $path = $request->file('school_logo')->store('settings', 'public');
            Setting::updateOrCreate(
                ['key' => 'school_logo'],
                ['value' => Storage::url($path)]
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Pengaturan berhasil diperbarui.'
        ]);
    }
}
