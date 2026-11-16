<?php

use App\Models\Setting;
use Carbon\Carbon;
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
 * Public URL of the school logo: the bundled one, or the one uploaded under Settings if the
 * bundled file is missing.
 */
function groupLogoUrl(): ?string
{
    if (file_exists(public_path('images/dcs-logo.png'))) {
        return asset('images/dcs-logo.png').'?v='.filemtime(public_path('images/dcs-logo.png'));
    }

    $logo = getSettings('logo');

    return $logo !== 'NA' && filled($logo) && file_exists(public_path('logo/'.$logo)) ? asset('logo/'.$logo) : null;
}

/**
 * Absolute file path of the school logo, for PDFs.
 */
function groupLogoPath(): ?string
{
    if (file_exists(public_path('images/dcs-logo.png'))) {
        return public_path('images/dcs-logo.png');
    }

    $logo = getSettings('logo');

    return $logo !== 'NA' && filled($logo) && file_exists(public_path('logo/'.$logo)) ? public_path('logo/'.$logo) : null;
}

/**
 * Number shown on the bell icon: currently the due/overdue reminders.
 */
function notifications()
{
    return count(getReminders());
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

function getReminders()
{
    $today = Carbon::today();

    $msg = [];
    foreach (DB::table('reminders')->where('status', 'On')->get(['name', 'end_date', 'days_to_remind']) as $r) {
        $days = (int) $today->diffInDays(Carbon::parse($r->end_date), false);
        if ($days > $r->days_to_remind) {
            continue;
        }

        if ($days > 0) {
            $msg[] = $r->name.' is due in '.$days.' days';
        } elseif ($days < 0) {
            $msg[] = $r->name.' is overdue by '.(0 - $days).' days';
        } else {
            $msg[] = $r->name.' is due today';
        }
    }

    return $msg;
}
