<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Support\Settings;
use Illuminate\Http\Request;

/** Pengaturan sistem (layar 24): nilai default aturan bisnis yang dapat diubah tanpa mengubah kode. */
class SettingsController extends Controller
{
    public function edit()
    {
        return view('admin.settings', ['values' => Settings::all(), 'fields' => Settings::EDITABLE]);
    }

    public function update(Request $request)
    {
        $rules = [];
        foreach (Settings::EDITABLE as $key => [$label, $type]) {
            $rules[$key] = $type === 'int' ? 'required|integer|min:0|max:100000' : 'required|string|max:200';
        }
        $data = $request->validate($rules);
        if ($data['passing_score'] > 100) {
            return back()->withErrors(['passing_score' => 'Passing grade maksimal 100.']);
        }
        $before = Settings::all();
        foreach (Settings::EDITABLE as $key => [, $type]) {
            $value = $type === 'int' ? (int) $data[$key]
                : collect(explode(',', $data[$key]))->map(fn ($v) => (int) trim($v))->filter(fn ($v) => $v >= 0)->unique()->sortDesc()->values()->all();
            Settings::set($key, $value);
        }
        AuditLogger::log('settings.update', 'Setting', array_intersect_key($before, Settings::EDITABLE), array_intersect_key(Settings::all(), Settings::EDITABLE));

        return back()->with('success', 'Pengaturan disimpan. Berlaku untuk program/penugasan baru; pengaturan tes per program tidak berubah otomatis.');
    }
}
