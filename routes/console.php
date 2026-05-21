<?php

declare(strict_types=1);

use App\Models\AppSetting;
use Illuminate\Support\Facades\Schedule;

$isEnabled = fn () => (int) AppSetting::get('log_cleanup_days', '0') > 0;
$isSchedule = fn (string $value) => fn () => $isEnabled() && AppSetting::get('log_cleanup_schedule', 'weekly') === $value;

Schedule::command('logs:cleanup')->dailyAt('03:00')->when($isSchedule('daily'))->withoutOverlapping();
Schedule::command('logs:cleanup')->weeklyOn(1, '03:00')->when($isSchedule('weekly'))->withoutOverlapping();
Schedule::command('logs:cleanup')->monthlyOn(1, '03:00')->when($isSchedule('monthly'))->withoutOverlapping();
