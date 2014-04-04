<?php
// This plugin is for Moodle - http://moodle.org/
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
 *
 * @package mod_publication
 * @author Andreas Windbichler
 * @copyright TSC
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/publication/backup/moodle2/restore_publication_stepslib.php');

class restore_publication_activity_task extends restore_activity_task {

    /**
     * Define (add) particular settings this activity can have.
     */
    protected function define_my_settings() {
        // No particular settings for this activity.
    }

    /**
     * Define (add) particular steps this activity can have.
     */
    protected function define_my_steps() {
        // Assignment only has one structure step.
        $this->add_step(new restore_publication_activity_structure_step('publication_structure', 'publication.xml'));
    }

    /**
     * Define the contents in the activity that must be
     * processed by the link decoder.
     *
     * @return array
     */
    static public function define_decode_contents() {
        $contents = array();

        $contents[] = new restore_decode_content('publication', array('intro'), 'publication');

        return $contents;
    }

    /**
     * Define the decoding rules for links belonging
     * to the activity to be executed by the link decoder.
     *
     * @return array of restore_decode_rule
     */
    static public function define_decode_rules() {
        $rules = array();

        $rules[] = new restore_decode_rule('PUBLICATIONVIEWBYID',
                                           '/mod/publication/view.php?id=$1',
                                           'course_module');
        $rules[] = new restore_decode_rule('PUBLICATIONINDEX',
                                           '/mod/publication/index.php?id=$1',
                                           'course_module');

        return $rules;

    }

    /**
     * Define the restore log rules that will be applied
     * by the {@link restore_logs_processor} when restoring
     * assign logs. It must return one array
     * of {@link restore_log_rule} objects.
     *
     * @return array of restore_log_rule
     */
    static public function define_restore_log_rules() {
        $rules = array();

        $rules[] = new restore_log_rule('publication', 'add', 'view.php?id={course_module}', '{publication}');
        $rules[] = new restore_log_rule('publication', 'update', 'view.php?id={course_module}', '{publication}');
        $rules[] = new restore_log_rule('publication', 'view', 'view.php?id={course_module}', '{publication}');

        return $rules;
    }

    /**
     * Define the restore log rules that will be applied
     * by the {@link restore_logs_processor} when restoring
     * course logs. It must return one array
     * of {@link restore_log_rule} objects
     *
     * Note this rules are applied when restoring course logs
     * by the restore final task, but are defined here at
     * activity level. All them are rules not linked to any module instance (cmid = 0)
     *
     * @return array
     */
    static public function define_restore_log_rules_for_course() {
        $rules = array();

        return $rules;
    }

}