<?php

declare(strict_types=1);

namespace Snmptraps\Web;

/**
 * Date / time inputs with Tabler components:
 *  - date: Tabler Datepicker (vanilla-calendar-pro), data-bs-toggle="datepicker", YYYY-MM-DD
 *  - time: Tabler InputMask (IMask), data-mask="00:00", HH:MM
 *
 * A date+time field "x" is posted as x_date + x_time; combine() joins them to "Y-m-d H:i"
 * (and still accepts a single "x" value, e.g. from old links).
 */
final class DateTimeField
{
    /** date + time inputs for "Y-m-d H:i[:s]" values */
    public static function dateTime(string $name, ?string $value, string $timePlaceholder = '00:00'): string
    {
        $value = trim((string) $value);
        $date = $value !== '' ? substr(str_replace('T', ' ', $value), 0, 10) : '';
        $time = $value !== '' ? substr(str_replace('T', ' ', $value), 11, 5) : '';
        return '<div class="row g-2 flex-nowrap">'
            . '<div class="col">' . self::date($name . '_date', $date) . '</div>'
            . '<div class="col-auto" style="width:7.5rem">' . self::time($name . '_time', $time, $timePlaceholder) . '</div>'
            . '</div>';
    }

    public static function date(string $name, string $value): string
    {
        return '<div class="input-icon"><span class="input-icon-addon"><i class="ti ti-calendar"></i></span>'
            . '<input type="text" class="form-control" name="' . e($name) . '" value="' . e($value) . '" placeholder="YYYY-MM-DD"'
            . ' autocomplete="off" data-bs-toggle="datepicker" data-bs-date-format="YYYY-MM-DD"></div>';
    }

    /** HH:MM input; stored HH:MM:SS values are shown without seconds */
    public static function time(string $name, ?string $value, string $placeholder = '00:00'): string
    {
        return '<div class="input-icon"><span class="input-icon-addon"><i class="ti ti-clock"></i></span>'
            . '<input type="text" class="form-control" name="' . e($name) . '" value="' . e(substr(trim((string) $value), 0, 5)) . '"'
            . ' placeholder="' . e($placeholder) . '" autocomplete="off" inputmode="numeric" data-mask="00:00"></div>';
    }

    /**
     * Posted value of a dateTime() field as "Y-m-d H:i" ('' when no date is given).
     *
     * @param array<string,mixed> $input
     */
    public static function combine(array $input, string $name, string $defaultTime = '00:00'): string
    {
        if (!array_key_exists($name . '_date', $input)) {
            return is_string($input[$name] ?? null) ? trim($input[$name]) : '';
        }
        $date = is_string($input[$name . '_date']) ? trim($input[$name . '_date']) : '';
        $time = is_string($input[$name . '_time'] ?? null) ? trim($input[$name . '_time']) : '';
        if ($date === '') {
            return '';
        }
        return $date . ' ' . ($time !== '' ? $time : $defaultTime);
    }
}
