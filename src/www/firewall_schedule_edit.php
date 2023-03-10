<?php

/*
 * Copyright (C) 2018 Fabian Franz
 * Copyright (C) 2014-2015 Deciso B.V.
 * Copyright (C) 2004 Scott Ullrich <sullrich@gmail.com>
 * Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 * OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */

require_once('guiconfig.inc');
require_once('filter.inc');

// Constants for localization that need to be used for static class properties
define('L10N_JAN', _('January'));
define('L10N_FEB', _('February'));
define('L10N_MAR', _('March'));
define('L10N_APR', _('April'));
define('L10N_MAY', _('May'));
define('L10N_JUN', _('June'));
define('L10N_JUL', _('July'));
define('L10N_AUG', _('August'));
define('L10N_SEP', _('September'));
define('L10N_OCT', _('October'));
define('L10N_NOV', _('November'));
define('L10N_DEC', _('December'));
define('L10N_MON', _('Mon'));
define('L10N_TUE', _('Tue'));
define('L10N_WED', _('Wed'));
define('L10N_THU', _('Thu'));
define('L10N_FRI', _('Fri'));
define('L10N_SAT', _('Sat'));
define('L10N_SUN', _('Sun'));

trait ErrorTrait {
    private $_errors = [];

    private function _setError(string $message): void {
        $this->_errors[] = $message;
    }

    final public function hasErrors(): bool {
        return !empty($this->_errors);
    }

    public function getErrors(): array {
        return $this->_errors;
    }

    private function _mergeErrors(array $errors): void {
        $this->_errors = array_merge($this->_errors, $errors);
    }
}

class TimeRange implements JsonSerializable
{
    use ErrorTrait;

    private static $_months = [
        L10N_JAN,
        L10N_FEB,
        L10N_MAR,
        L10N_APR,
        L10N_MAY,
        L10N_JUN,
        L10N_JUL,
        L10N_AUG,
        L10N_SEP,
        L10N_OCT,
        L10N_NOV,
        L10N_DEC
    ];

    private static $_days = [
        L10N_MON,
        L10N_TUE,
        L10N_WED,
        L10N_THU,
        L10N_FRI,
        L10N_SAT,
        L10N_SUN
    ];

    private $_description;
    private $_dates_selected;
    private $_dates_selected_text;
    private $_start_time;
    private $_stop_time;

    public function __construct(array $time_range) {
        $this->_initDescription($time_range);
        $this->_initStartAndStop($time_range);
        $this->_initSelectedDates($time_range);
    }

    private function _escape($value) {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML401);
    }

    private function _isValidTime(string $time): bool {
        return preg_match('/^[0-9]+:[0-9]+$/', $time);
    }

    private function _validateTimes(): bool {
        $is_error = false;

        if (!$this->_isValidTime($this->_start_time)) {
            $this->_setError(_(sprintf('Invalid start time: %s', $this->_start_time)));
            $is_error = true;
        }

        if (!$this->_isValidTime($this->_stop_time)) {
            $this->_setError(_(sprintf('Invalid stop time: %s', $this->_stop_time)));
            $is_error = true;
        }

        return !$is_error;
    }

    private function _validateSelectedDates(): bool {
        $this->_dates_selected = trim($this->_dates_selected);

        if ($this->_dates_selected) {
            return true;
        }

        $this->_setError(_('One or more days/dates must be selected before the time range can be added.'));
        return false;
    }

    private function _initDescription(array $time_range): void {
        if (@$time_range['description']) {
            $this->_description = $this->_escape($time_range['description']);
            return;
        }

        if (@$time_range['rangedescr']) {
            $this->_description = $this->_escape(rawurldecode($time_range['rangedescr']));
            return;
        }

        $this->_description = '';
    }

    private function _initStartAndStop(array $time_range): void {
        $this->_start_time = '';
        $this->_stop_time = '';

        if (isset($time_range['start_time']) && isset($time_range['stop_time'])) {
            $this->_start_time = $this->_escape($time_range['start_time']);
            $this->_stop_time = $this->_escape($time_range['stop_time']);
        }

        if (isset($time_range['hour'])) {
            [$start_time, $stop_time] = explode('-', $time_range['hour']);
            $this->_start_time = $this->_escape($start_time);
            $this->_stop_time = $this->_escape($stop_time);
        }

        $this->_validateTimes();
    }

    private function _getOrdinal($date) {
        return date('jS', mktime(1, 1, 1, 1, $date));
    }

    private function _initRepeatingWeeklyDays(string $selected_days_of_week): void {
        $this->_dates_selected = $this->_escape($selected_days_of_week);
        $days_selected_text = [];
        $day_range_start = null;

        // ISO 8601 days
        $days_of_week = explode(',', $selected_days_of_week);

        foreach ($days_of_week as $i => $day_of_week) {
            $day_of_week = (int)$day_of_week;

            if (!$day_of_week) {
                continue;
            }

            $day_range_start = $day_range_start ?? $day_of_week;
            $next_selected_day = $days_of_week[$i + 1];

            // Continue to the next day when working on a range (i.e. Mon-Thu)
            if (($day_of_week + 1) == $next_selected_day) {
                continue;
            }

            $start_day = self::$_days[$day_range_start - 1];
            $stop_day = self::$_days[$day_of_week - 1];

            if ($day_of_week == $day_range_start) {
                $days_selected_text[] = $start_day;
                $day_range_start = null;
                continue;
            }

            $days_selected_text[] = sprintf('%s-%s', $start_day, $stop_day);
            $day_range_start = null;
        }

        $this->_dates_selected_text = sprintf(
            '(Weekly) %s',
            $this->_escape(implode(', ', $days_selected_text))
        );
    }

    private function _initRepeatingMonthlyDates(string $selected_dates): void {
        $date_range_start = null;
        $dates_selected = [];
        $dates_selected_text = [];
        $selected_dates = explode(',', $selected_dates);

        foreach ($selected_dates as $selection_num => $date) {
            $date = (int)$date;
            $dates_selected[] = sprintf('d%d', $date);
            $date_range_start = $date_range_start ?? $date;
            $next_selected_date = (int)$selected_dates[$selection_num + 1];

            // Continue to the next day when working on a range (i.e. 6-8)
            if (($date + 1) == $next_selected_date) {
                continue;
            }

            // Friendly description for individual repeating day
            if ($date == $date_range_start) {
                $dates_selected_text[] = $this->_getOrdinal($date);
                $date_range_start = null;
                continue;
            }

            // Friendly description for a range of dates
            $dates_selected_text[] = sprintf(
                '%s-%s',
                $this->_getOrdinal($date_range_start),
                $this->_getOrdinal($date)
            );

            $date_range_start = null;
        }

        $this->_dates_selected = $this->_escape(implode(',', $dates_selected));

        $this->_dates_selected_text = sprintf(
            '(Monthly) %s',
            $this->_escape(implode(', ', $dates_selected_text))
        );
    }

    final public static function getDayCellID(int $month, int $day): string {
        return sprintf('m%dd%d', $month, $day);
    }

    private function _initNonRepeatingDates(
        string $selected_months,
        string $selected_dates
    ): void {
        $date_range_start = null;
        $dates_selected = [];
        $dates_selected_text = [];
        $selected_months = explode(',', $selected_months);
        $selected_dates = explode(',', $selected_dates);

        foreach ($selected_months as $selection_num => $month) {
            $month = (int)$month;
            $date = (int)$selected_dates[$selection_num];

            $dates_selected[] = $this->getDayCellID($month, $date);

            $date_range_start = $date_range_start ?? $date;
            $month_short = substr(self::$_months[$month - 1], 0, 3);
            $next_selected_month = (int)$selected_months[$selection_num + 1];
            $next_selected_date = (int)$selected_dates[$selection_num + 1];

            // Continue to the next day when working on a range (i.e. Feb 6-8)
            if ($month == $next_selected_month && ($date + 1) == $next_selected_date) {
                continue;
            }

            // Friendly description for individual non-repeating day
            if ($date == $date_range_start) {
                $dates_selected_text[] = sprintf(
                    '%s %s',
                    $month_short,
                    $this->_getOrdinal($date)
                );

                $date_range_start = null;
                continue;
            }

            // Friendly description for a range of dates
            $dates_selected_text[] = sprintf(
                '%s %s-%s',
                $month_short,
                $date_range_start,
                $date
            );

            $date_range_start = null;
        }

        $this->_dates_selected = $this->_escape(implode(',', $dates_selected));
        $this->_dates_selected_text = $this->_escape(implode(', ', $dates_selected_text));
    }

    private function _isRepeatingWeekly(?array $time_range = null): bool {
        if ($time_range) {
// TODO: $time_range['days_of_week'] is here for future support whenever it's ready
            return (!empty($time_range['position'])
                || !empty($time_range['days_of_week'])
            );
        }

// TODO: Refactor to check for 'w'
        return !(strstr($this->_dates_selected, 'm')
            || strstr($this->_dates_selected, 'd')
        );
    }

    private function _isRepeatingMonthly(?array $time_range = null): bool {
        if ($time_range) {
// TODO: $time_range['dates'] is here for future support whenever it's ready
            return (!empty($time_range['day']) && empty($time_range['month'])
                || !empty($time_range['dates']) && empty($time_range['months'])
            );
        }

        return strstr($this->_dates_selected, 'd') && !strstr($this->_dates_selected, 'm');
    }

    private function _initSelectedDates(array $time_range): void {
        if (isset($time_range['dates_selected'])) {
            $this->_dates_selected = $this->_escape($time_range['dates_selected']);
        }

        if ($this->_isRepeatingWeekly($time_range)) {
            $this->_initRepeatingWeeklyDays($time_range['position']);
            $this->_validateSelectedDates();
            return;
        }

        if ($this->_isRepeatingMonthly($time_range)) {
            $this->_initRepeatingMonthlyDates($time_range['day']);
            $this->_validateSelectedDates();
            return;
        }

        if (isset($time_range['month'])) {
            $this->_initNonRepeatingDates(
                $time_range['month'],
                $time_range['day']
            );
        }

        $this->_validateSelectedDates();
    }

    final public static function getJSONMonths(): string {
        return json_encode(self::$_months);
    }

    final public static function getJSONDays(): string {
        return json_encode(self::$_days);
    }

    final public static function getDays(): array {
        return self::$_days;
    }

    private function _getNonRepeatingMonthAndDays(): ?array {
        if ($this->_isRepeatingWeekly() || $this->_isRepeatingMonthly()) {
            $this->_setError(_('Malformed non-repeating selected dates'));
            return null;
        }

        $selected_dates = explode(',', $this->_dates_selected);
        $months = [];
        $days = [];

        foreach ($selected_dates as $selected_date) {
            if (!$selected_date) {
                continue;
            }

            [$month, $day] = explode('d', $selected_date);
            $months[] = ltrim($month, 'm');
            $days[] = $day;
        }

        return [
            'month' => implode(',', $months),
            'day' => implode(',', $days)
        ];
    }

    final public function getDataForSave(): ?array {
        if (!($this->_validateTimes() && $this->_validateSelectedDates())) {
            return null;
        }

        $time_range = [
            'hour' => sprintf('%s-%s', $this->_start_time, $this->_stop_time),
            'rangedescr' => rawurlencode($this->_description)
        ];

        if ($this->_isRepeatingWeekly()) {
            $time_range['position'] = $this->_dates_selected;
            return $time_range;
        }

        if ($this->_isRepeatingMonthly()) {
            $selected_dates = explode(',', $this->_dates_selected);
            $days = [];

            foreach ($selected_dates as $date) {
                if (!$date) {
                    continue;
                }

                $days[] = ltrim($date, 'd');
            }

            return array_merge($time_range, [
                'month' => '',
                'day' => implode(',', $days)
            ]);
        }

        $month_and_days = $this->_getNonRepeatingMonthAndDays();

        if (!$month_and_days) {
            return null;
        }

        return array_merge($time_range, $month_and_days);
    }

    final public function jsonSerialize() {
        return [
            'description' => $this->_description,
            'dates_selected' => $this->_dates_selected,
            'dates_selected_text' => $this->_dates_selected_text,
            'start_time' => $this->_start_time,
            'stop_time' => $this->_stop_time
        ];
    }
}

class Schedule
{
    use ErrorTrait;

    private const RETURN_URL = 'firewall_schedule.php';

    private $_rules;
    private $_config;
    private $_id;
    private $_data;

    public function __construct() {
        $this->_rules = config_read_array('filter', 'rule') ?? [];
        $this->_config = &config_read_array('schedules', 'schedule');

        $this->_initSchedule();
    }

    final public function returnToSchedules(): void {
        header(url_safe(sprintf('Location: /%s', self::RETURN_URL)));
        exit;
    }

    final public function getReturnURL(): string {
        return self::RETURN_URL;
    }

    private function _escapeData(): void {
        $this->_data = (array)$this->_data;
        legacy_html_escape_form_data($this->_data);
        $this->_data = (object)$this->_data;
    }

    private function _setData(?array $data): void {
        // Invalid request
        if ($data === null) {
            $this->returnToSchedules();
        }

        $this->_data = (object)[
            'name' => $data['name'] ?? null,
            'description' => $data['descr'] ?? null,
            'time_ranges' => [],
            'is_disabled' => (bool)$data['is_disabled']
        ];

        // NOTE: legacy_html_escape_form_data() doesn't escape objects;
        // TimeRange will perform its own escaping
        $this->_escapeData();

        $time_ranges = $data['timerange'] ?? [];
        foreach ($time_ranges as $time_range) {
            $time_range = new TimeRange($time_range);

            if ($time_range->hasErrors()) {
                $this->_mergeErrors($time_range->getErrors());
                continue;
            }

            $this->_data->time_ranges[] = $time_range;
        }
    }

    final public function getData(?string $prop = null) {
        if ($prop !== null) {
            return $this->_data->{$prop} ?? null;
        }

        return $this->_data;
    }

    private function _initSchedule(): void {
        // Add button clicked
        if (empty($_GET)) {
            $this->_setData([]);
            return;
        }

        // Calendar button clicked on the Rules screen
        if (isset($_GET['name'])) {
            foreach ($this->_config as $id => $schedule) {
                if ($schedule['name'] != $_GET['name']) {
                    continue;
                }

                $this->_id = $id;
                $this->_setData($schedule);
                return ;
            }

            // Invalid request
            $this->returnToSchedules();
        }

        // Clone button clicked
        if (isset($_GET['dup'])) {
            $this->_id = (int)$_GET['dup'];
            $schedule = @$this->_config[$this->_id];

            // Only the time range is needed when cloning because users should enter
            // a new name and/or description
            if ($schedule) {
                $schedule = ['timerange' => $schedule['timerange']];
            }

            // NOTE: Schedule is being cloned; so $_id MUST NOT be set
            $this->_id = null;

            $this->_setData($schedule);
            return;
        }

        // Edit button clicked
        if (isset($_GET['id'])) {
            $this->_id = (int)$_GET['id'];
            $this->_setData(@$this->_config[$this->_id]);
            return;
        }

        // Invalid request
        $this->returnToSchedules();
    }

    final public function hasID(): bool {
        return ($this->_id !== null);
    }

    final public function getID(): ?int {
        return $this->_id;
    }

    final public function getHTMLReferences(): string {
        if (!($this->_data->name && $this->_rules)) {
            return '';
        }

        $references = [];

        foreach ($this->_rules as $rule) {
            if (@$rule['sched'] != $this->_data->name) {
                continue;
            }

            $references[] = sprintf('<li>%s</li>', trim($rule['descr']));
        }

        return implode("\n", $references);
    }

    final public function getHTMLMonthOptions(): string {
        $month = (int)date('n');
        $year = (int)date('Y');
        $options = [];

        for ($m = 0; $m < 12; $m++) {
            $options[] = sprintf(
                '<option value="%s">%s</option>',
                date('Y-m', mktime(0, 0, 0, $month, 1, $year)),
                date('F (Y)', mktime(0, 0, 0, $month, 1, $year))
            );

            if ($month++ < 12) {
                continue;
            }

            $month = 1;
            $year++;
        }

        return implode("\n", $options);
    }

    final public function getHTML24HourOptions(): string {
        $options = [];

        for ($h = 0; $h < 24; $h++) {
            $options[] = sprintf('<option value="%d">%d</option>', $h, $h);
        }

        return implode("\n", $options);
    }

    final public function getHTMLMinuteOptions(): string {
        $minutes = ['00', '15', '30', '45', '59'];
        $options = [];

        foreach ($minutes as $minute) {
            $options[] = sprintf(
                '<option value="%s">%s</option>',
                $minute,
                $minute
            );
        }

        return implode("\n", $options);
    }

    final public function getHTMLDaysOfWeekButtons(): string {
        $days = TimeRange::getDays();
        $buttons = [];

        foreach ($days as $index => $day) {
            $day_of_week = $index + 1;

            $input = sprintf('<input type="checkbox" autocomplete="off" /> %s', $day);

            $buttons[] = sprintf(
                '<label id="day-of-week-%d" class="btn btn-default" onclick="toggleRepeatingWeeklyDay(%d)">%s</label>',
                $day_of_week,
                $day_of_week,
                $input
            );
        }

        return sprintf(
            '<div id="days-of-week" class="btn-group" data-toggle="buttons">%s</div>',
            implode("\n", $buttons)
        );
    }

    final public function getHTMLDaysOfMonthButtons(): string {
        // ISO 8601 days
        $monday = 1;
        $sunday = 7;
        $last_date = 31;

        $day_of_week = $monday;
        $groups = [];

        for ($date = 1; $date <= $last_date; $date++) {
            if ($day_of_week == $monday) {
                $groups[] = '<div class="btn-group" data-toggle="buttons">';
            }

            $input = sprintf('<input type="checkbox" autocomplete="off" /> %d', $date);

            $groups[] = sprintf(
                '<label id="day-of-month-%d" class="btn btn-default" onclick="toggleRepeatingMonthlyDate(%d)">%s</label>',
                $date,
                $date,
                $input
            );

            $day_of_week = ($date % $sunday) + 1;

            if ($day_of_week == $monday || $date == $last_date) {
                $groups[] = '</div>';
            }
        }

        return sprintf('<div id="days-of-month">%s</div>', implode("\n", $groups));
    }

    private function _getHTMLCalendarHeaders(): string {
        $days = TimeRange::getDays();
        $headers = [];

        foreach ($days as $index => $day_of_week) {
            $headers[] = sprintf(
                '<td class="calendar-header-day" onclick="toggleRepeatingWeeklyDay(%d)">%s</td>',
                $index + 1,
                $day_of_week
            );
        }

        return implode("\n", $headers);
    }

    private function _getHTMLCalendarBody($month, $year): string {
        // ISO 8601 days
        $monday = 1;
        $sunday = 7;

        $day_of_week = $monday;
        $day_of_week_start = (int)date('N', mktime(0, 0, 0, $month, 1, $year));
        $day_of_month = 1;
        $last_day_of_month = (int)date('t', mktime(0, 0, 0, $month, 1, $year));

        $total_cells = ceil($last_day_of_month / $sunday) * $sunday;
        $cell_text = '';
        $rows = [];

        for ($cell_num = 1; $cell_num <= $total_cells; $cell_num++) {
            $cell_attribs = '';

            if ($day_of_week == $monday) {
                $rows[] = '<tr>';
            }

            if ($day_of_week == $day_of_week_start || $cell_text) {
                $cell_id = TimeRange::getDayCellID($month, $day_of_month);
                $cell_text = '';

                if ($day_of_month <= $last_day_of_month) {
                    $cell_attribs = sprintf(
                        ' id="%s" class="calendar-day p%d" onclick="toggleNonRepeatingDate(\'%s\')" data-state="white"',
                        $cell_id,
                        $day_of_week,
                        $cell_id
                    );

                    $cell_text = $day_of_month;
                }

                $day_of_month++;
            }

            $rows[] = sprintf('<td%s>%s</td>', $cell_attribs, $cell_text);

            $day_of_week = ($cell_num % $sunday) + 1;

            if ($day_of_week == $monday) {
                $rows[] = '</tr>';
            }
        }

        return implode("\n", $rows);
    }

    final public function getHTMLCalendar(): string {
        $month = (int)date('n');
        $year = (int)date('Y');

        $calendar = [];

        for ($m = 0; $m < 12; $m++) {
            $id = date('Y-m', mktime(0, 0, 0, $month, 1, $year));
            $display = (!$m) ? 'block' : 'none';

            $headers = $this->_getHTMLCalendarHeaders();
            $body = $this->_getHTMLCalendarBody($month, $year);

            $calendar[] = <<<HTML
                      <div id="{$id}" style="position: relative; display: {$display};">
                        <table class="table table-condensed table-bordered">
                          <thead>
                            <tr>
                              {$headers}
                            </tr>
                          </thead>
                          <tbody>
                            {$body}
                          </tbody>
                        </table>
                      </div>
HTML;

            if ($month++ < 12) {
                continue;
            }

            $month = 1;
            $year++;
        }

        return implode("\n", $calendar);
    }

    final public function getJSONTimeRanges(): string {
        if (!isset($this->_data->time_ranges)) {
            return '[]';
        }

        return json_encode($this->_data->time_ranges);
    }

    private function _validateName(string $name): void {
        if (!preg_match('/^[a-zA-Z0-9_\-]{1,32}$/', $name)) {
            $this->_setError(_('Schedule name cannot exceed 32 characters and must only contain the following: a-z, A-Z, 0-9, _'));
        }
        if (in_array(strtolower($name), ['lan', 'wan'])) {
            $this->_setError(_(sprintf('Schedule cannot be named %s.', $name)));
        }
        if (empty($name)) {
            $this->_setError(_('Schedule name is required.'));
        }

        // Check for name conflicts
        //
        // NOTE: $_id will be null when saving a new schedule
        foreach ($this->_config as $config_id => $schedule) {
            if ($schedule['name'] != $name || $config_id == $this->_id) {
                continue;
            }

            $this->_setError(_('A schedule with this name already exists.'));
            break;
        }
    }

    private function _sortConfigByName(): void {
        if (empty($this->_config)) {
            return;
        }

        usort($this->_config, function ($a, $b) {
            return strcmp($a['name'], $b['name']);
        });
    }

    final public function save($data): bool {
        $this->_data = (object)$data;

        $this->_id = @$this->_data->id;
        $this->_id = (!isset($this->_config[$this->_id])) ? null : (int)$this->_id;

        $this->_validateName($this->_data->name);

        // Parse time ranges
        $this->_data->time_ranges = [];

        if ($this->_data->dates) {
            foreach ($this->_data->dates as $range_num => $selected_dates) {
                $time_range = new TimeRange([
                    'start_time' => $this->_data->start_times[$range_num],
                    'stop_time' => $this->_data->stop_times[$range_num],
                    'description' => $this->_data->range_descriptions[$range_num],
                    'dates_selected' => $selected_dates
                ]);

                if ($time_range->hasErrors()) {
                    $this->_mergeErrors($time_range->getErrors());
                    continue;
                }

                $data = $time_range->getDataForSave();

                if ($time_range->hasErrors()) {
                    $this->_mergeErrors($time_range->getErrors());
                    continue;
                }

                $this->_data->time_ranges[] = $data;
            }
        }

        if (!$this->_data->time_ranges) {
            $this->_setError(_('The schedule must have at least one time range configured.'));
        }

        if ($this->hasErrors()) {
            return false;
        }

        $this->_id = $this->_id ?? count($this->_config);
        $this->_config[$this->_id] = [
            'name' => $this->_data->name,
            'descr' => $this->_data->description,
            'timerange' => $this->_data->time_ranges,
            'is_disabled' => (string)(@$this->_data->is_disabled == 'yes')
        ];

        $this->_sortConfigByName();
        write_config();
        filter_configure();

        return true;
    }
}

$schedule = new Schedule();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $schedule->save($_POST)) {
    $schedule->returnToSchedules();
}

include('head.inc');
?>
<body>
<style>
label {
  display: inline;
}
#show_all_help_page {
  cursor: pointer;
}
button.btn {
  margin-right: 5px;
}
.btn-group {
  width: 100%;
  margin: 3px 0;
}
.btn-group > label.btn {
  width: 14.3% !important;
}
.input-group > .dropdown.form-control > select.dropdown-hour + button.dropdown-toggle {
  border-top-left-radius: 3px;
  border-bottom-left-radius: 3px;
}
.input-group > .dropdown.form-control > select.dropdown-minute + button.dropdown-toggle {
  border-top-right-radius: 3px;
  border-bottom-right-radius: 3px;
}
.calendar-nav {
  text-align: center;
  border-bottom-width: 0 !important;
  padding: 3px;
}
.calendar-nav-button {
  text-align: center;
  line-height: 34px;
  width: 60%;
  visibility: hidden;
  cursor: pointer;
}
.calendar-header-day {
  text-decoration: underline;
  text-align: center;
  cursor: pointer;
  border-bottom-width: 0 !important;
}
.calendar-day {
  text-align: center;
  cursor: pointer;
}
.time-range-configured {
  word-wrap: break-word;
  max-width: initial !important;
  border: 0 !important;
  border-radius: 0 !important;
}
</style>
<script>
//<![CDATA[
function _addSelectedDates(cell_id_or_day_of_week) {
    $('#iform')[0]._selected_dates[cell_id_or_day_of_week] = 1;
}

function _removeSelectedDates(cell_id_or_day_of_week) {
    const iform = $('#iform')[0];

    if (!(cell_id_or_day_of_week in iform._selected_dates))
        return;

    delete iform._selected_dates[cell_id_or_day_of_week];
}

function _getSelectedDates(is_sort = false) {
    let selected_dates = Object.keys($('#iform')[0]._selected_dates || {});

    selected_dates = selected_dates.filter(function(selected_day) {
        return !!String(selected_day).trim();
    });

    if (!is_sort)
        return selected_dates;

    selected_dates.sort();

    return selected_dates;
}

function _isSelectedDatesEmpty() {
    return !_getSelectedDates().length;
}

function _clearSelectedDates() {
    // Use an object instead of an array to leverage hash-sieving for faster
    // lookups and to prevent duplicates
    $('#iform')[0]._selected_dates = {};
}

function injectFlashError(error) {
    const main_content = $('.page-content-main > .container-fluid > .row');

    let flash_box = main_content.find('div.col-xs-12');
    flash_box = (!flash_box.length) ? $('<div class="col-xs-12"></div>') : flash_box;
    flash_box.empty();

    const alert_box = $('<div class="alert alert-danger" role="alert"></div>');
    const close_button = $('<button type="button" class="close" data-dismiss="alert"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>');

    alert_box.append(close_button);
    alert_box.append(error);
    flash_box.append(alert_box);
    main_content.prepend(flash_box);

    const _clearBlurTimer = function() {
        if (!flash_box._timer)
            return;

        clearTimeout(flash_box._timer);
        delete flash_box._timer;
    };

    flash_box.attr('tabindex', -1).focus();

    flash_box.blur(function() {
        flash_box.off('blur');
        _clearBlurTimer();

        flash_box._timer = setTimeout(function() {
            _clearBlurTimer();

            if (document.activeElement === flash_box[0])
                return;

            flash_box.empty();
        }, 1000);
    });
}

function showSelectedMonth() {
    const month_select = $(this);

    // The first month will always be visible by default when the page loads;
    // otherwise, the visible_month property should be set
    const visible_month = month_select.prop('visible_month') || month_select.prop('options')[0].value;
    $(`#${visible_month}`).css('display', 'none');

    const selected_month = month_select.prop('selectedOptions')[0].value;
    month_select.prop('visible_month', selected_month);
    $(`#${selected_month}`).css('display', 'block');
}

function _selectMonth(calendar_month_id) {
    if (!(calendar_month_id && $(`#${calendar_month_id}`).length))
        return;

    const month_select = $('#month-select');

    month_select.val(calendar_month_id);
    month_select.selectpicker('refresh');

    showSelectedMonth.bind(month_select[0])();
}

function toggleNonRepeatingDate(cell_id, is_select_month = false) {
    const day_cell = $(`#${cell_id}`);

    if (!day_cell.length)
        return injectFlashError('Failed to find the correct day to toggle. Please save your schedule and try again.');

    // ISO 8601 day
    const day_of_week = day_cell.attr('class').slice('-1');
    const day_cells = $(`.p${day_of_week}`);

    if (day_cell.attr('data-state') !== 'white') {
        day_cell.attr('data-state', 'white');
        _removeSelectedDates(cell_id);

        // Ensure that the repeating day gets removed...
        _removeSelectedDates(day_of_week);

        // ... then each individual non-repeating day gets selected when needed
        return day_cells.filter('[data-state = "lightcoral"]').each(function(i, cell) {
            $(cell).attr('data-state', 'red');
            _addSelectedDates(cell.id)
        });
    }

    day_cell.attr('data-state', 'red');
    _addSelectedDates(cell_id);

    // When manually selecting individual non-repeating dates, ensure that each
    // individual day gets replaced with the day of the week instead so that the
    // data will get saved simply as a repeating day
    if (day_cells.length === day_cells.filter('[data-state != "white"]').length) {
        day_cells.attr('data-state', 'lightcoral');

        day_cells.filter('[data-state = "lightcoral"]').each(function(i, cell) {
            _removeSelectedDates(cell.id);
        });

        _addSelectedDates(day_of_week);
    }

    if (!is_select_month)
        return;

    // Ensure that the month for the first highlighted cell comes into view
    _selectMonth(day_cell.closest('table').parent().prop('id'));
}

// ISO 8601 day
function toggleRepeatingWeeklyDay(day_of_week) {
    const day_cells = $(`.p${day_of_week}`);

    if (!day_cells.length)
        return injectFlashError('Failed to find the correct days to toggle. Please save your schedule and try again.');

    const selected_days = day_cells.filter('[data-state != "white"]');

    if (day_cells.length === selected_days.length) {
        day_cells.attr('data-state', 'white');
        return _removeSelectedDates(day_of_week);
    }

    // Ensure that all individual non-repeating days get removed
    selected_days.each(function(i, cell) {
        _removeSelectedDates(cell.id);
    });

    day_cells.attr('data-state', 'lightcoral');
    _addSelectedDates(day_of_week);
}

function toggleRepeatingMonthlyDate(date) {
    for (let month = 1; month <= 12; month++) {
        let selected_day = `d${date}`;
        let date_cell = $(`#m${month}d${date}`);

        if (!date_cell.length)
            continue;

        if (date_cell.attr('data-state') !== 'white') {
            date_cell.attr('data-state', 'white');
            _removeSelectedDates(selected_day);
            continue;
        }

        date_cell.attr('data-state', 'red');
        _addSelectedDates(selected_day);
    }
}

function _activateButton(button) {
    $(button).addClass('active');
}

function _deactivateButton(button) {
    $(button).removeClass('active').blur();
}

function _clearCalendar(is_clear_description = false) {
    _clearSelectedDates();

    const month_select = $('#month-select');
    const visible_month = month_select.prop('visible_month') || month_select.prop('options')[0].value;

    $(`#${visible_month}`)
        .parent()
        .find('tbody td[data-state]')
        .filter('[data-state != "white"]')
        .attr('data-state', 'white');

    $('#start-hour').val('0');
    $('#start-minute').val('00');
    $('#stop-hour').val('23');
    $('#stop-minute').val('59');
    $('.selectpicker.form-control').selectpicker('refresh');

    if (is_clear_description)
        $('#range-description').val('');
}

function _clearEntryModeInputs(is_clear_description = false) {
    _clearCalendar(is_clear_description);

    $('#days-of-week label.btn').each(function(index, button) {
        _deactivateButton(button);
    });

    $('#days-of-month label.btn').each(function(index, button) {
        _deactivateButton(button);
    });
}

function warnBeforeClearCalender() {
    const def = $.Deferred();

    $(this).blur();

    if (_isSelectedDatesEmpty())
        return def.resolve();

    BootstrapDialog.show({
        'type': BootstrapDialog.TYPE_DANGER,
        'title': '<?= _('Clear Selections?') ?>',
        'message': '<div style="margin: 10px;"><?= _('Are you sure you want to clear your selection(s)? All unsaved changes will be lost!') ?></div>',

        'buttons': [
            {
                'label': '<?= _('Cancel') ?>',
                'cssClass': 'btn btn-default',
                'action': function(dialog) {
                    dialog.close();
                    def.reject();
                }
            },
            {
                'label': '<?= _('Clear') ?>',
                'cssClass': 'btn btn-danger',
                'action': function(dialog) {
                    _clearEntryModeInputs();
                    dialog.close();
                    def.resolve();
                }
            }
        ]
    });

    return def.promise();
}

function resetTimeRangeInputRows() {
    _deactivateButton('#mode-repeat-weekly-button');
    _deactivateButton('#mode-repeat-monthly-button');
    _deactivateButton('#mode-custom-button');

    $('#range-repeat-weekly-row').hide();
    $('#range-repeat-monthly-row').hide();
    $('#range-custom-row').hide();
    $('#range-time-row').hide();
    $('#range-description-row').hide();
    $('#range-buttons-row').hide();

    _clearEntryModeInputs(true);
}

function removeTimeRange(is_confirm = false) {
    const _confirmBeforeRemove = function() {
        const def = $.Deferred();

        if (!is_confirm)
            return def.resolve();

        BootstrapDialog.show({
            'type': BootstrapDialog.TYPE_DANGER,
            'title': '<?= _('Remove Time Range?') ?>',
            'message': '<div style="margin: 10px;"><?= _('Are you sure you want to remove this time range?') ?></div>',

            'buttons': [
                {
                    'label': '<?= _('Cancel') ?>',
                    'cssClass': 'btn btn-default',
                    'action': function(dialog) {
                        dialog.close();
                        def.reject();
                    }
                },
                {
                    'label': '<?= _('Remove') ?>',
                    'cssClass': 'btn btn-danger',
                    'action': function(dialog) {
                        dialog.close();
                        def.resolve();
                    }
                }
            ]
        });

        return def.promise();
    };

    const remove_button = $(this);

    remove_button.blur();

    $.when(_confirmBeforeRemove()).then(function() {
        remove_button.closest('tr').remove();
    });

    // Stops the onClick event from propagating
    return false;
}

function _isRepeatingWeekly(selection) {
    return !isNaN(parseInt(selection));
}

function _isRepeatingMonthly(selection) {
    selection = String(selection);

    return selection.includes('d') && !selection.includes('m');
}

function _isNonRepeating(selection) {
    selection = String(selection);

    return selection.includes('d') && selection.includes('m');
}

function _injectTimeRange(
    dates_text,
    dates,
    start_time,
    stop_time,
    range_description,
    is_clear_calendar = true
) {
    const tbody = $('#calendar tbody');
    const tr = $('<tr></tr>');
    const edit_click = `return editTimeRange.bind(this)('${dates}', '${start_time}', '${stop_time}', '${range_description}')`;
    const delete_click = `return removeTimeRange.bind(this)(true)`;

    tbody.append(tr);
    tr.append(`<td><span>${dates_text}</span> <input type="hidden" name="dates[]" value="${dates}" /></td>`);
    tr.append(`<td><input type="text" name="start_times[]" value="${start_time}" class="time-range-configured" readonly="readonly" /></td>`);
    tr.append(`<td><input type="text" name="stop_times[]" value="${stop_time}" class="time-range-configured" readonly="readonly" /></td>`);
    tr.append(`<td><input type="text" name="range_descriptions[]" value="${range_description}" class="time-range-configured" readonly="readonly" /></td>`);
    tr.append(`<td><a href="#" class="btn btn-default" onclick="${edit_click}"><span class="fa fa-pencil fa-fw"></span></a></td>`);
    tr.append(`<td><a href="#" class="btn btn-default" onclick="${delete_click}"><span class="fa fa-trash fa-fw"></span></a></td>`);

    if (!is_clear_calendar)
        return;

    resetTimeRangeInputRows();
}

function _injectRepeatingWeeklyDays(
    days_selected,
    start_time,
    stop_time,
    description
) {
    const _days = <?= TimeRange::getJSONDays() ?>;
    let day_range_start = null;
    let days_selected_text = [];

    // Prepare the friendly label to make it easier to read on the list of
    // "Configured Time Ranges"
    days_selected.forEach(function(day_of_week, i) {
        day_of_week = parseInt(day_of_week);

        if (!(day_of_week && !isNaN(day_of_week)))
            return;

        let next_selected_day = parseInt(days_selected[i + 1]);

        day_range_start = (!day_range_start) ? day_of_week : day_range_start;

        // Continue to the next day when working on a range (i.e. Mon-Wed)
        if ((day_of_week + 1) === next_selected_day)
            return;

        let start_day = _days[day_range_start - 1];
        let end_day = _days[day_of_week - 1];

        if (day_of_week === day_range_start) {
            days_selected_text.push(start_day);
            day_range_start = null;
            return;
        }

        days_selected_text.push(`${start_day}-${end_day}`);
        day_range_start = null;
    });

    days_selected_text = days_selected_text.join(', ');

    _injectTimeRange(
        `(<?= _('Weekly') ?>) ${days_selected_text} `,
        days_selected.join(','),
        start_time,
        stop_time,
        description
    );
}

function _injectRepeatingMonthlyDates(
    dates_selected,
    start_time,
    stop_time,
    description
) {
    const _getPreparedDate = function(selection) {
        return parseInt((selection || '').slice(1));
    };

    let date_range_start = null;
    let dates_selected_text = [];

    // Prepare the friendly label to make it easier to read on the list of
    // "Configured Time Ranges"
    dates_selected.forEach(function(selected, i) {
        let date = _getPreparedDate(selected);

        if (!(date && !isNaN(date)))
            return;

        date_range_start = (!date_range_start) ? date : date_range_start;

        const next_date = _getPreparedDate(dates_selected[i + 1]);

        // Continue to the next day when working on a range (i.e. 6-8)
        if ((date + 1) === next_date)
            return;

        if (date === date_range_start) {
            dates_selected_text.push(date);
            date_range_start = null;
            return;
        }

        dates_selected_text.push(`${date_range_start}-${date}`);
        date_range_start = null;
    });

    dates_selected_text = dates_selected_text.join(', ');

    _injectTimeRange(
        `(<?= _('Monthly') ?>) ${dates_selected_text}`,
        dates_selected.join(','),
        start_time,
        stop_time,
        description
    );
}

function _injectNonRepeatingDates(
    dates_selected,
    start_time,
    stop_time,
    description
) {
    const _getPreparedMonthAndDate = function(selection) {
        let month, date;
        [month, date] = (selection || '').split('d');

        return {
            'month': parseInt(month.slice(1)),
            'date': parseInt(date)
        };
    };

    const _months = <?= TimeRange::getJSONMonths() ?>;
    let date_range_start = null;
    let dates_selected_text = [];

    // Prepare the friendly label to make it easier to read on the list of
    // "Configured Time Ranges"
    dates_selected.forEach(function(selected, i) {
        selected = _getPreparedMonthAndDate(selected);

        if (!(selected.month && !isNaN(selected.month)))
            return;

        date_range_start = (!date_range_start) ? selected.date : date_range_start;

        const next_selected = _getPreparedMonthAndDate(dates_selected[i + 1]);

        // Continue to the next day when working on a range (i.e. Feb 6-8)
        if (selected.month === next_selected.month
            && (selected.date + 1) === next_selected.date
        ) {
            return;
        }

        const month_short = _months[selected.month - 1].slice(0, 3);

        if (selected.date === date_range_start) {
            dates_selected_text.push(`${month_short} ${selected.date}`);
            date_range_start = null;
            return;
        }

        dates_selected_text.push(`${month_short} ${date_range_start}-${selected.date}`);
        date_range_start = null;
    });

    _injectTimeRange(
        dates_selected_text.join(', '),
        dates_selected.join(','),
        start_time,
        stop_time,
        description
    );
}

function addTimeRange() {
    const start_hour = parseInt($('#start-hour').val());
    const start_minute = $('#start-minute').val();
    const stop_hour = parseInt($('#stop-hour').val());
    const stop_minute = $('#stop-minute').val();
    const start_time = `${start_hour}:${start_minute}`;
    const stop_time = `${stop_hour}:${stop_minute}`;
    const description = $('#range-description').val();

    if (_isSelectedDatesEmpty())
        return injectFlashError('One or more days/dates must be selected before the time range can be added.');

    if (start_hour > stop_hour
        || (start_hour === stop_hour && parseInt(start_minute) > parseInt(stop_minute))
    ) {
        return injectFlashError('Start Time must not be ahead of the Stop Time.');
    }

    let dates_selected = _getSelectedDates(true);

    if (!dates_selected.length)
        return;

    if (_isRepeatingWeekly(dates_selected)) {
        return _injectRepeatingWeeklyDays(
            dates_selected,
            start_time,
            stop_time,
            description
        );
    }

    if (_isRepeatingMonthly(dates_selected)) {
        return _injectRepeatingMonthlyDates(
            dates_selected,
            start_time,
            stop_time,
            description
        );
    }

    if (_isNonRepeating(dates_selected)) {
        return _injectNonRepeatingDates(
            dates_selected,
            start_time,
            stop_time,
            description
        );
    }

    injectFlashError('Unable to add time range. Please check your selections and try again.');
}

function _askToAddOrClearTimeRange(range_description) {
    const def = $.Deferred();

    BootstrapDialog.show({
        'type': BootstrapDialog.TYPE_PRIMARY,
        'title': '<?= _('Modified Time Range In Progress') ?>',
        'message': '<div style="margin: 10px;">'
            + `<strong>Range Description:</strong> ${range_description || '<em>N/A</em>'}`
            + '\n\n'
            + '<?= _('What would you like to do with the time range that you have in progress?') ?>'
            + '</div>',

        'buttons': [
            {
                'label': '<?= _('Cancel') ?>',
                'cssClass': 'btn btn-default',
                'action': function(dialog) {
                    dialog.close();
                }
            },
            {
                'label': '<?= _('Clear') ?>',
                'cssClass': 'btn btn-danger',
                'action': function(dialog) {
                    dialog.close();
                    $.when(warnBeforeClearCalender()).then(def.resolve);
                }
            },
            {
                'label': '<?= _('Add & Continue') ?>',
                'cssClass': 'btn btn-primary',
                'action': function(dialog) {
                    addTimeRange();
                    dialog.close();
                    def.resolve();
                }
            }
        ]
    });

    return def.promise();
}

function _showTimeRangeInputRows() {
    _clearEntryModeInputs();

    $('#range-time-row').show();
    $('#range-description-row').show();
    $('#range-buttons-row').show();
}

function showRepeatWeeklyRow() {
    const _doShow = function() {
        _activateButton('#mode-repeat-weekly-button');
        $('#range-repeat-weekly-row').show();

        _deactivateButton('#mode-repeat-monthly-button');
        _deactivateButton('#mode-custom-button');
        $('#range-repeat-monthly-row').hide();
        $('#range-custom-row').hide();

        _showTimeRangeInputRows();
    };

    if (!_isSelectedDatesEmpty()) {
        // NOTE: askToAddOrClearTimeRange() will only resolve the promise
        $.when(_askToAddOrClearTimeRange($('#range-description').val())).then(_doShow);

        // Stops the onClick event from propagating
        return false;
    }

    _doShow();

    // Stops the onClick event from propagating
    return false;
}

function showRepeatMonthlyRow() {
    const _doShow = function() {
        _activateButton('#mode-repeat-monthly-button');
        $('#range-repeat-monthly-row').show();

        _deactivateButton('#mode-repeat-weekly-button');
        _deactivateButton('#mode-custom-button');
        $('#range-repeat-weekly-row').hide();
        $('#range-custom-row').hide();

        _showTimeRangeInputRows();
    };

    if (!_isSelectedDatesEmpty()) {
        // NOTE: askToAddOrClearTimeRange() will only resolve the promise
        $.when(_askToAddOrClearTimeRange($('#range-description').val())).then(_doShow);

        // Stops the onClick event from propagating
        return false;
    }

    _doShow();

    // Stops the onClick event from propagating
    return false;
}

function showCustomRow() {
    const _doShow = function() {
        _activateButton('#mode-custom-button');
        $('#range-custom-row').show();

        _deactivateButton('#mode-repeat-weekly-button');
        _deactivateButton('#mode-repeat-monthly-button');
        $('#range-repeat-weekly-row').hide();
        $('#range-repeat-monthly-row').hide();

        _showTimeRangeInputRows();
    };

    if (!_isSelectedDatesEmpty()) {
        // NOTE: askToAddOrClearTimeRange() will only resolve the promise
        $.when(_askToAddOrClearTimeRange($('#range-description').val())).then(_doShow);

        // Stops the onClick event from propagating
        return false;
    }

    _doShow();

    // Stops the onClick event from propagating
    return false;
}

function editTimeRange(dates, start_time, stop_time, range_description) {
    const _toggleMode = function() {
        if (dates.includes('m'))
            return showCustomRow();

        if (dates.includes('d')) {
            showRepeatMonthlyRow();

            return dates.split(',').forEach(function(date) {
                _activateButton(`#day-of-month-${date.slice(1)}`);
            });
        }

        showRepeatWeeklyRow();

        dates.split(',').forEach(function(day) {
            _activateButton(`#day-of-week-${day}`);
        });
    };

    const _doEdit = function() {
        removeTimeRange.bind(this)();
        _clearEntryModeInputs();
        _toggleMode();

        let start_hour, start_min, stop_hour, stop_min;
        [start_hour, start_min] = start_time.split(':');
        [stop_hour, stop_min] = stop_time.split(':');

        $('#start-hour').val(start_hour);
        $('#start-minute').val(start_min);
        $('#stop-hour').val(stop_hour);
        $('#stop-minute').val(stop_min);
        $('#range-description').val(range_description);

        let is_select_month = true;

        dates.split(',').forEach(function(date) {
            if (!date)
                return;

            if (date.includes('m')) {
                toggleNonRepeatingDate(date, is_select_month);
                return is_select_month = false;
            }

            if (date.includes('d'))
                return toggleRepeatingMonthlyDate(date.slice(1));

            toggleRepeatingWeeklyDay(date);
        });

        $('.selectpicker').selectpicker('refresh');
    }.bind(this);

    $(this).blur();

    if (!_isSelectedDatesEmpty()) {
        // NOTE: askToAddOrClearTimeRange() will only resolve the promise
        $.when(_askToAddOrClearTimeRange($('#range-description').val())).then(_doEdit);

        // Stops the onClick event from propagating
        return false;
    }

    _doEdit();

    // Stops the onClick event from propagating
    return false;
}

function _initCalendar() {
    const month_select = $('#month-select');
    const month_select_options = month_select.prop('options');
    const total_options = month_select_options.length;
    const month_left = $('#month-left');
    const month_right = $('#month-right');

    const _toggleLeftRightVisibility = function(selected_index) {
        month_left.css(
            'visibility',
            (selected_index <= 0) ? 'hidden' : 'visible'
        );
        month_right.css(
            'visibility',
            ((selected_index + 1) >= total_options) ? 'hidden' : 'visible'
        );
    };

    month_select.change(function() {
        _toggleLeftRightVisibility($(this).prop('selectedIndex'));
    });

    month_left.click(function() {
        let index = month_select.prop('selectedIndex');

        if (index > 0) {
            index--;
            _selectMonth((month_select_options[index] || {}).value);
        }

        _toggleLeftRightVisibility(index);
    });

    month_right.click(function() {
        let index = month_select.prop('selectedIndex');

        if (index < total_options) {
            index++;

            _selectMonth((month_select_options[index] || {}).value);
        }

        _toggleLeftRightVisibility(index);
    });

    _clearCalendar(true);
    _toggleLeftRightVisibility(0);
}

function warnBeforeSave() {
    if (!_isSelectedDatesEmpty()) {
        // NOTE: askToAddOrClearTimeRange() will only resolve the promise
        $.when(_askToAddOrClearTimeRange($('#range-description').val())).then(function() {
            $('#submit').click();
        });

        // Stops the onSubmit event from propagating
        return false;
    }

    return true;
}


$(function() {
    // NOTE: Needed to prevent hook_stacked_form_tables() from breaking the
    // calendar's CSS when selecting dates, as well as making the selections
    // disappear when the screen gets resized or the orientation changes
    //
    // @see /www/javascripts/opnsense_legacy.js
    const _onResize = function() {
        $('#iform .table-condensed td').css('background-color', '');
    };

    $(window).resize(_onResize);
    _onResize();

    const time_ranges = <?= $schedule->getJSONTimeRanges() ?>;

    time_ranges.forEach(function(time_range) {
        _injectTimeRange(
            time_range.dates_selected_text,
            time_range.dates_selected,
            time_range.start_time,
            time_range.stop_time,
            time_range.description,
            false
        );
    });

    resetTimeRangeInputRows();
    _initCalendar();
});
//]]>
</script>

<?php include('fbegin.inc'); ?>

  <section class="page-content-main">
    <div class="container-fluid">
      <div class="row">
<?php
if ($schedule->hasErrors()) {
    print_input_errors($schedule->getErrors());
}
?>
        <section class="col-xs-12">
          <form method="post" name="iform" id="iform">
            <div class="content-box tab-content __mb">
              <table class="table table-striped opnsense_standard_table_form">
                <tbody>
                  <tr>
                    <td style="width: 15%">
                      <strong><?= _('Schedule Information') ?></strong>
                    </td>
                    <td style="width: 85%; text-align: right">
                      <small><?= _('full help') ?></small>
                      <em id="show_all_help_page" class="fa fa-toggle-off text-danger"></em>
                    </td>
                  </tr>
                  <tr>
                    <td>
                      <a id="help_for_disabled" href="#" class="showhelp"><em class="fa fa-info-circle"></em></a>
                      <label for="is_disabled"><?= _('Disabled') ?></label>
                    </td>
                    <td>
                      <input type="checkbox" name="is_disabled" id="is_disabled" value="yes"
                             <?= ($schedule->getData('is_disabled')) ? 'checked="checked"' : '' ?> />
                      <?= _('Disable this schedule') ?>
                      <div class="hidden" data-for="help_for_disabled">
                        <br />
                        <?= _('Set this option to disable this schedule without removing it from the list.') ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td>
<?php $references = $schedule->getHTMLReferences(); ?>
                      <?= ($references) ? '<em class="fa fa-info-circle text-muted"></em>' : '' ?>
                      <label for="name"><?= _('Name') ?></label>
                    </td>
                    <td>
<?php if ($references): ?>
                      <?= $schedule->getData('name') ?>
                      <div class="text-warning" style="margin-top: 10px;">
                        <?= _('The name cannot be modified because this schedule is referenced by the following rules:') ?>
                        <ul style="margin-top: 10px;">
                        <?= $references ?>
                        </ul>
                      </div>
                      <input name="name" type="hidden" value="<?= $schedule->getData('name') ?>" />
<?php else: ?>
                      <input type="text" name="name" id="name" value="<?= $schedule->getData('name') ?>" />
<?php endif ?>
                    </td>
                  </tr>
                  <tr>
                    <td>
                      <a id="help_for_description" href="#" class="showhelp"><em class="fa fa-info-circle"></em></a>
                      <label for="description"><?= _('Description') ?></label>
                    </td>
                    <td>
                      <input type="text" name="description" id="description" value="<?= $schedule->getData('description') ?>" />
                      <div class="hidden" data-for="help_for_description">
                        <br />
                        <?= _('You may enter a description here for your reference (not parsed).') ?>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="content-box tab-content __mb">
              <table class="table table-striped opnsense_standard_table_form">
                <tbody>
                  <tr>
                    <th colspan="2"><?= _('Configured Time Ranges') ?></th>
                  </tr>
                  <tr>
                    <td>&nbsp;</td>
                    <td>
                      <table id="calendar">
                        <tbody>
                          <tr>
                            <td style="width: 35%;"><?= _('Day or Dates') ?></td>
                            <td style="width: 12%;"><?= _('Start Time') ?></td>
                            <td style="width: 11%;"><?= _('Stop Time') ?></td>
                            <td style="width: 42%;"><?= _('Description') ?></td>
                          </tr>
                        </tbody>
                      </table>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="content-box tab-content __mb">
              <table class="table table-striped opnsense_standard_table_form">
                <tbody>
                  <tr>
                    <th colspan="2"><?= _('Edit Time Range') ?></th>
                  </tr>
                  <tr>
                    <td style="width: 150px;">
                      <a id="help_for_entry_mode" href="#" class="showhelp"><em class="fa fa-info-circle"></em></a>
                      <label><?= _('Entry Mode') ?></label>
                    </td>
                    <td>
                      <button id="mode-repeat-weekly-button" type="button"
                              class="btn btn-default"
                              onclick="return showRepeatWeeklyRow()">
                        <?= html_safe(_('Repeat Weekly')) ?>
                      </button>
                      <button id="mode-repeat-monthly-button" type="button"
                              class="btn btn-default"
                              onclick="return showRepeatMonthlyRow()">
                        <?= html_safe(_('Repeat Monthly')) ?>
                      </button>
                      <button id="mode-custom-button" type="button"
                              class="btn btn-default"
                              onclick="return showCustomRow()">
                        <?= html_safe(_('Custom')) ?>
                      </button>

                      <div class="hidden" data-for="help_for_entry_mode">
                        <br />
                        <?= _('Use "Repeat Weekly" or "Repeat Monthly" to configure a time range for repeating days/dates or "Custom" to choose dates on a calendar.') ?>
                      </div>
                    </td>
                  </tr>

                  <tr id="range-repeat-weekly-row">
                    <td>
                      <a id="help_for_repeat_weekly" href="#" class="showhelp"><em class="fa fa-info-circle"></em></a>
                      <?= _('Repeat Weekly') ?>
                    </td>
                    <td>
                      <?= $schedule->getHTMLDaysOfWeekButtons() ?>

                      <div class="hidden" data-for="help_for_repeat_weekly">
                        <br />
                        <?= _('Select the days of the week when the time range will be active.') ?>
                      </div>

                      <?php
                      // Hack to make sure the striping continues for the table
                      // rows below because the tables above appear to disrupt
                      // the striping for the rows that follow
                      ?>
                      <table style="visibility: hidden;">
                        <tr><td></td></tr>
                      </table>
                    </td>
                  </tr>

                  <tr id="range-repeat-monthly-row">
                    <td>
                      <a id="help_for_repeat_monthly" href="#" class="showhelp"><em class="fa fa-info-circle"></em></a>
                      <?= _('Repeat Monthly') ?>
                    </td>
                    <td>
                      <?= $schedule->getHTMLDaysOfMonthButtons() ?>

                      <div class="hidden" data-for="help_for_repeat_monthly">
                        <br />
                        <?= _('Select the days of the month when the time range will be active.') ?>
                      </div>

                      <?php
                      // Hack to make sure the striping continues for the table
                      // rows below because the tables above appear to disrupt
                      // the striping for the rows that follow
                      ?>
                      <table style="visibility: hidden;">
                        <tr><td></td></tr>
                      </table>
                    </td>
                  </tr>

                  <tr id="range-custom-row">
                    <td>
                      <a id="help_for_specific_dates" href="#" class="showhelp"><em class="fa fa-info-circle"></em></a>
                      <?= _('Custom') ?>
                    </td>
                    <td>
                      <table class="table table-condensed table-bordered" style="border-top-width: 2px;">
                        <thead>
                          <tr>
                            <td class="calendar-nav" style="border-right-width: 0;">
                              <div id="month-left" class="calendar-nav-button">
                                <span class="fa fa-chevron-left"></span>
                              </div>
                            </td>
                            <td colspan="5" class="calendar-nav" style="border: 0;">
                              <select id="month-select" class="selectpicker"
                                      data-width="auto" data-live-search="true"
                                      onchange="showSelectedMonth.bind(this)();">
                                  <?= $schedule->getHTMLMonthOptions(); ?>
                              </select>
                            </td>
                            <td class="calendar-nav" style="border-left-width: 0;">
                              <div id="month-right" class="calendar-nav-button" style="float: right;">
                                <span class="fa fa-chevron-right"></span>
                              </div>
                            </td>
                          </tr>
                        </thead>
                      </table>
                      <?= $schedule->getHTMLCalendar(); ?>

                      <?php
                      // Hack to make sure the striping continues for the table
                      // rows below because the tables above appear to disrupt
                      // the striping for the rows that follow
                      ?>
                      <table style="visibility: hidden;">
                        <tr><td></td></tr>
                      </table>

                      <div class="hidden" data-for="help_for_specific_dates">
                        <br />
                        <?= _('Click an individual date to select that date only. Click the appropriate day header to select all occurrences of that day.') ?>
                      </div>
                    </td>
                  </tr>

                  <tr id="range-time-row">
                    <td>
                      <a id="help_for_time" href="#" class="showhelp"><em class="fa fa-info-circle"></em></a>
                      <?= _('Time') ?>
                    </td>
                    <td>
                      <table>
                        <tr>
                          <td><?= _('Start Time') ?></td>
                          <td><?= _('Stop Time') ?></td>
                        </tr>
                        <tr>
                          <td>
                            <div class="input-group">
                              <select id="start-hour" class="selectpicker form-control dropdown-hour"
                                      data-width="auto" data-size="5" data-live-search="true">
                                <?= $schedule->getHTML24HourOptions() ?>
                              </select>
                              <select id="start-minute" class="selectpicker form-control dropdown-minute"
                                      data-width="auto" data-size="5" data-live-search="true">
                                  <?= $schedule->getHTMLMinuteOptions() ?>
                              </select>
                            </div>
                          </td>
                          <td>
                            <div class="input-group">
                              <select id="stop-hour" class="selectpicker form-control dropdown-hour"
                                      data-width="auto" data-size="5" data-live-search="true">
                                <?= $schedule->getHTML24HourOptions() ?>
                              </select>
                              <select id="stop-minute" class="selectpicker form-control dropdown-minute"
                                      data-width="auto" data-size="5" data-live-search="true">
                                <?= $schedule->getHTMLMinuteOptions() ?>
                              </select>
                            </div>
                          </td>
                        </tr>
                      </table>

                      <div class="hidden" data-for="help_for_time">
                        <br />
                        <?= _('Select the time range for the day(s) selected above. A full day is 0:00-23:59.') ?>
                      </div>
                    </td>
                  </tr>

                  <tr id="range-description-row">
                    <td>
                      <a id="help_for_timerange_desc" href="#" class="showhelp"><em class="fa fa-info-circle"></em></a>
                      <label for="range-description"><?= _('Description') ?></label>
                    </td>
                    <td>
                      <input type="text" id="range-description" />
                      <div class="hidden" data-for="help_for_timerange_desc">
                        <br />
                        <?= _('You may enter a description here for your reference (not parsed).') ?>
                      </div>
                    </td>
                  </tr>

                  <tr id="range-buttons-row">
                    <td>&nbsp;</td>
                    <td>
                      <button type="button" class="btn btn-default"
                              onclick="resetTimeRangeInputRows()">
                        <?= html_safe(_('Cancel')) ?>
                      </button>
                      <button type="button" class="btn btn-danger"
                              onclick="warnBeforeClearCalender.bind(this)()">
                        <?= html_safe(_('Clear')) ?>
                      </button>
                      <button type="button" class="btn btn-primary"
                              onclick="addTimeRange.bind(this)()">
                          <?= html_safe(_('Add')) ?>
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="content-box tab-content __mb">
              <table class="table table-striped opnsense_standard_table_form">
                <tbody>
                  <tr>
                    <td>&nbsp;</td>
                    <td>
                      <?= ($schedule->hasID()) ? sprintf('<input name="id" type="hidden" value="%d" />', $schedule->getID()) : '' ?>

                      <div style="float: right;">
                        <button type="button" class="btn btn-default"
                                onclick="window.location.href='<?= $schedule->getReturnURL() ?>'">
                          <?= html_safe(_('Cancel')) ?>
                        </button>
                        <button type="submit" name="submit" id="submit"
                                class="btn btn-primary"
                                onclick="return warnBeforeSave()">
                          <?= html_safe(_('Save')) ?>
                        </button>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

          </form>
        </section>
      </div>
    </div>
  </section>

<?php include('foot.inc'); ?>
