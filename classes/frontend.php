<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Front-end class for the other course completion availability condition.
 *
 * @package availability_othercompleted
 * @copyright MU DOT MY PLT <support@mu.my>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace availability_othercompleted;

/**
 * Front-end class for the other course completion availability condition.
 *
 * @package availability_othercompleted
 * @copyright MU DOT MY PLT <support@mu.my>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class frontend extends \core_availability\frontend {
    /**
     * @var array Cached init parameters
     */
    protected $cacheparams = [];

    /**
     * @var string IDs of course, cm, and section for cache (if any)
     */
    protected $cachekey = '';

    /**
     * @var array Cache for the initialization parameters
     */
    protected $cacheinitparams = [];

    /**
     * Gets a list of JavaScript strings required for this module.
     *
     * @return array Array of required string identifiers
     */
    protected function get_javascript_strings() {
        return ['option_complete', 'label_cm', 'label_completion'];
    }

    /**
     * Gets parameters for the JavaScript init function.
     *
     * @param \stdClass $course The course object
     * @param ?\cm_info $cm Course module info object (optional)
     * @param ?\section_info $section Section info object (optional)
     * @return array Array of parameters for the JavaScript function
     */
    protected function get_javascript_init_params(
        $course,
        ?\cm_info $cm = null,
        ?\section_info $section = null
    ) {
        // Use cached result if available. The cache is just because we call it
        // twice (once from allow_add) so it's nice to avoid doing all the
        // print_string calls twice.
        $cachekey = $course->id . ',' . ($cm ? $cm->id : '') . ($section ? $section->id : '');
        if ($cachekey !== $this->cachekey) {
            // Get list of activities on course which have completion values,
            // to fill the dropdown.
            $context = \context_course::instance($course->id);
            $datcms = [];
            global $DB;
            $sql2 = "SELECT * FROM {course}
                    ORDER BY fullname ASC";
            $other = $DB->get_records_sql($sql2);
            foreach ($other as $othercm) {
                // Disable not created course and default course.
                if (($othercm->category > 0) && ($othercm->id != $course->id)) {
                        $datcms[] = (object)[
                            'id' => $othercm->id,
                            'name' => format_string($othercm->fullname, true, ['context' => $context]),
                        ];
                }
            }
            $this->cachekey = $cachekey;
            $this->cacheinitparams = [$datcms];
        }
        return $this->cacheinitparams;
    }

    /**
     * Determines whether this availability condition can be added to a course/module.
     *
     * @param \stdClass $course The course object
     * @param ?\cm_info $cm Course module info object (optional)
     * @param ?\section_info $section Section info object (optional)
     * @return bool True if this condition can be added, false otherwise
     */
    protected function allow_add(
        $course,
        ?\cm_info $cm = null,
        ?\section_info $section = null
    ) {
        global $CFG;

        // Check if completion is enabled for the course.
        require_once($CFG->libdir . '/completionlib.php');
        $info = new \completion_info($course);
        if (!$info->is_enabled()) {
            return false;
        }

        // Check if there's at least one other module with completion info.
        $params = $this->get_javascript_init_params($course, $cm, $section);
        return ((array)$params[0]) != false;
    }
}
