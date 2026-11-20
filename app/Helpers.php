<?php

use App\Models\SchoolRequest;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

function getRoles()
{
    return DB::table('roles')
        ->select('id', 'name')
        ->orderBy('name')
        ->get();
}

function getSettings($settingName)
{
    $settings = Setting::get();

    $value = 'NA';
    foreach ($settings as $s) {
        if ($s->name == $settingName) {
            return $s->value;
        }
    }

    return $value;
}

/**
 * Organisation name from Settings, falling back to APP_NAME.
 */
function groupName(): string
{
    $name = getSettings('group_name');

    return $name === 'NA' || blank($name) ? config('app.name') : $name;
}

/**
 * Public URL of the school logo, a file bundled with the app.
 */
function groupLogoUrl(): ?string
{
    $path = public_path('images/dcs-logo.png');

    return file_exists($path) ? asset('images/dcs-logo.png').'?v='.filemtime($path) : null;
}

/**
 * Absolute file path of the school logo, for PDFs.
 */
function groupLogoPath(): ?string
{
    $path = public_path('images/dcs-logo.png');

    return file_exists($path) ? $path : null;
}

/**
 * Number shown on the bell icon: the requests waiting for the signed-in user's action.
 */
function notifications()
{
    return Auth::check() ? SchoolRequest::awaitingActionBy(Auth::user())->count() + assignedToMe() : 0;
}

/**
 * Requests assigned to the signed-in user that they still have to carry out.
 */
function assignedToMe()
{
    return Auth::check()
        ? SchoolRequest::where('assigned_to', Auth::id())->where('status', SchoolRequest::STATUS_IN_PROGRESS)->count()
        : 0;
}

/**
 * Full amount with thousands separators, e.g. 1100000 -> "1,100,000".
 */
function formatAmount($value)
{
    return number_format(round((float) $value));
}

/**
 * Up to two uppercase initials from a name, e.g. "John Doe" -> "JD".
 */
function initials(string $name): string
{
    $words = array_filter(explode(' ', trim($name)));
    $initials = array_map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)), $words);

    return implode('', array_slice($initials, 0, 2)) ?: '?';
}

/**
 * A stored (UTC) date-time shown in Dar es Salaam time, e.g. "16 Nov 2026 17:30".
 */
function displayTime($dateTime, string $format = 'j M Y H:i'): string
{
    return $dateTime ? Carbon::parse($dateTime)->timezone('Africa/Dar_es_Salaam')->format($format) : '';
}

function formatCompactAmount(float $amount): string
{
    $absolute = abs($amount);

    if ($absolute >= 999500) {
        return number_format($amount / 1000000, 2).'M';
    }

    if ($absolute >= 1000) {
        return number_format($amount / 1000).'k';
    }

    return number_format($amount);
}
