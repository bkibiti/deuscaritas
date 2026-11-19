<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ConfigurationsController extends Controller
{
    /**
     * The settings this page edits. The logo is a file bundled with the app
     * (public/images/dcs-logo.png), not a setting.
     */
    public const KEYS = ['group_name', 'registration_number', 'slogan'];

    public function index(): View
    {
        $settings = Setting::whereIn('name', self::KEYS)->pluck('value', 'name');

        return view('configurations.index', compact('settings'));
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'group_name' => 'required|string|max:150',
            'registration_number' => 'nullable|string|max:100',
            'slogan' => 'nullable|string|max:255',
        ]);

        foreach (self::KEYS as $key) {
            $this->save($key, $data[$key] ?? null);
        }

        session()->flash('alert-success', 'Settings saved successfully!');

        return back();
    }

    private function save(string $key, ?string $value): void
    {
        Setting::where('name', $key)->update([
            'value' => $value,
            'updated_by' => auth()->id(),
        ]);
    }
}
