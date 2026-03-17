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
 * Unit tests for the other COURSE completion condition.
 *
 * NOTE: This plugin checks OTHER COURSE completion, not activity completion.
 *
 * @package availability_othercompleted
 * @copyright MU DOT MY PLT <support@mu.my>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace availability_othercompleted;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/completionlib.php');

/**
 * Unit tests for the other course completion condition.
 *
 * @package availability_othercompleted
 * @copyright MU DOT MY PLT <support@mu.my>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \availability_othercompleted\condition
 */
final class condition_test extends \advanced_testcase {
    /**
     * Setup test environment.
     */
    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        global $CFG;
        require_once($CFG->dirroot . '/availability/tests/fixtures/mock_info.php');
    }

    /**
     * Tests constructing and using condition to check if another COURSE is complete.
     */
    public function test_in_tree(): void {
        global $USER, $CFG, $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $CFG->enablecompletion = true;
        $CFG->enableavailability = true;
        $generator = $this->getDataGenerator();

        // Create current course (where restriction will be applied).
        $currentcourse = $generator->create_course(['enablecompletion' => 1]);

        // Create another course that must be completed first.
        $othercourse = $generator->create_course(['enablecompletion' => 1, 'fullname' => 'Other Course']);

        // Enrol user in both courses.
        $generator->enrol_user($USER->id, $currentcourse->id);
        $generator->enrol_user($USER->id, $othercourse->id);

        // Create availability condition: requires OTHER course to be complete.
        $info = new \core_availability\mock_info($currentcourse, $USER->id);
        $structure = (object)['op' => '|', 'show' => true, 'c' => [
                (object)['type' => 'othercompleted', 'cm' => (int)$othercourse->id,
                'e' => COMPLETION_COMPLETE]]];
        $tree = new \core_availability\tree($structure);

        // Initial check: user has NOT completed the other course.
        $result = $tree->check_available(false, $info, true, $USER->id);
        $this->assertFalse($result->is_available());

        // Mark the OTHER course as complete for this user.
        $ccompletion = new \stdClass();
        $ccompletion->course = $othercourse->id;
        $ccompletion->userid = $USER->id;
        $ccompletion->timecompleted = time();
        $DB->insert_record('course_completions', $ccompletion);

        // Now condition should be satisfied.
        $result = $tree->check_available(false, $info, true, $USER->id);
        $this->assertTrue($result->is_available());
    }

    /**
     * Tests the constructor including error conditions.
     */
    public function test_constructor(): void {
        // No parameters.
        $structure = new \stdClass();
        try {
            $cond = new condition($structure);
            $this->fail();
        } catch (\coding_exception $e) {
            $this->assertStringContainsString('Missing or invalid ->course', $e->getMessage());
        }

        // Invalid cm (not a number).
        $structure->cm = 'hello';
        try {
            $cond = new condition($structure);
            $this->fail();
        } catch (\coding_exception $e) {
            $this->assertStringContainsString('Missing or invalid ->course', $e->getMessage());
        }

        // Missing expected completion.
        $structure->cm = 42;
        try {
            $cond = new condition($structure);
            $this->fail();
        } catch (\coding_exception $e) {
            $this->assertStringContainsString('Missing or invalid ->e', $e->getMessage());
        }

        // Invalid expected completion value.
        $structure->e = 99;
        try {
            $cond = new condition($structure);
            $this->fail();
        } catch (\coding_exception $e) {
            $this->assertStringContainsString('Missing or invalid ->e', $e->getMessage());
        }

        // Successful construct with course ID (note: debug string says "course42").
        $structure->e = COMPLETION_COMPLETE;
        $cond = new condition($structure);
        $this->assertEquals('{othercompleted:course42 COMPLETE}', (string)$cond);
    }

    /**
     * Tests the save() function.
     */
    public function test_save(): void {
        $structure = (object)['cm' => 42, 'e' => COMPLETION_COMPLETE];
        $cond = new condition($structure);
        $saved = $cond->save();
        $this->assertEquals('othercompleted', $saved->type);
        // Note: Saves as 'course' property, not 'cm'.
        $this->assertEquals(42, $saved->course);
        $this->assertEquals(COMPLETION_COMPLETE, $saved->e);
    }

    /**
     * Tests the is_available and get_description functions with COURSE completion.
     */
    public function test_usage(): void {
        global $CFG, $DB, $USER;
        $this->resetAfterTest();

        $CFG->enablecompletion = true;
        $CFG->enableavailability = true;
        $generator = $this->getDataGenerator();

        // Create current course.
        $currentcourse = $generator->create_course(['enablecompletion' => 1]);

        // Create other course with a recognizable name.
        $othercourse = $generator->create_course([
            'enablecompletion' => 1,
            'fullname' => 'Required Course',
        ]);

        $user = $generator->create_user();
        $generator->enrol_user($user->id, $currentcourse->id);
        $generator->enrol_user($user->id, $othercourse->id);
        $this->setUser($user);

        $info = new \core_availability\mock_info($currentcourse, $user->id);

        // Test COMPLETE requirement when course is NOT complete.
        $cond = new condition((object)['cm' => (int)$othercourse->id, 'e' => COMPLETION_COMPLETE]);
        $this->assertFalse($cond->is_available(false, $info, true, $user->id));

        $information = $cond->get_description(false, false, $info);
        $information = \core_availability\info::format_info($information, $currentcourse);
        $this->assertStringContainsString('Required Course', $information);
        $this->assertStringContainsString('completed course', $information);

        // Test with NOT condition.
        $this->assertTrue($cond->is_available(true, $info, true, $user->id));

        // Mark course complete.
        $ccompletion = new \stdClass();
        $ccompletion->course = $othercourse->id;
        $ccompletion->userid = $user->id;
        $ccompletion->timecompleted = time();
        $DB->insert_record('course_completions', $ccompletion);

        // Now should be available.
        $this->assertTrue($cond->is_available(false, $info, true, $user->id));
        $this->assertFalse($cond->is_available(true, $info, true, $user->id));
    }

    /**
     * Tests completion_value_used static function with COURSE IDs.
     */
    public function test_completion_value_used(): void {
        global $CFG, $DB;
        $this->resetAfterTest();

        $CFG->enablecompletion = true;
        $CFG->enableavailability = true;
        $generator = $this->getDataGenerator();

        $course = $generator->create_course(['enablecompletion' => 1], ['createsections' => true]);
        $othercourse1 = $generator->create_course(['enablecompletion' => 1]);
        $othercourse2 = $generator->create_course(['enablecompletion' => 1]);
        $othercourse3 = $generator->create_course(['enablecompletion' => 1]);

        condition::wipe_static_cache();

        // Create a page with restriction based on othercourse1.
        $page = $generator->get_plugin_generator('mod_page')->create_instance([
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);

        $DB->set_field(
            'course_modules',
            'availability',
            '{"op":"|","show":true,"c":[' .
                '{"type":"othercompleted","e":1,"cm":' . $othercourse1->id . '}]}',
            ['id' => $page->cmid]
        );

        // Set section 1 to depend on othercourse2.
        $DB->set_field(
            'course_sections',
            'availability',
            '{"op":"|","show":true,"c":[' .
                '{"type":"othercompleted","e":1,"cm":' . $othercourse2->id . '}]}',
            ['course' => $course->id, 'section' => 1]
        );

        // Check: othercourse3 is not used, but othercourse1 and othercourse2 are.
        $this->assertTrue(condition::completion_value_used($course, $othercourse1->id));
        $this->assertTrue(condition::completion_value_used($course, $othercourse2->id));
        $this->assertFalse(condition::completion_value_used($course, $othercourse3->id));
    }

    /**
     * Tests the update_dependency_id() function.
     *
     * NOTE: Current implementation tries to update course IDs when course_modules
     * table is specified, which is incorrect but matches existing behavior.
     */
    public function test_update_dependency_id(): void {
        $cond = new condition((object)['cm' => 123, 'e' => COMPLETION_COMPLETE]);

        // Returns false for non-course_modules tables.
        $this->assertFalse($cond->update_dependency_id('course', 123, 456));
        $this->assertFalse($cond->update_dependency_id('frogs', 123, 456));

        // Returns false when ID doesn't match.
        $this->assertFalse($cond->update_dependency_id('course_modules', 999, 456));

        // Returns true when table is course_modules and ID matches (even though this stores course IDs).
        $this->assertTrue($cond->update_dependency_id('course_modules', 123, 456));
        $saved = $cond->save();
        $this->assertEquals(456, $saved->course);
    }
}
