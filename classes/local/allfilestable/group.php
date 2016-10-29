<?php
// This file is part of mod_publication for Moodle - http://moodle.org/
//
// It is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// It is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * allfilestable/group.php
 *
 * @package       mod_publication
 * @author        Andreas Hruska (andreas.hruska@tuwien.ac.at)
 * @author        Katarzyna Potocka (katarzyna.potocka@tuwien.ac.at)
 * @author        Philipp Hager (office@phager.at)
 * @copyright     2016 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license       http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_publication\local\allfilestable;

defined('MOODLE_INTERNAL') || die();

/**
 * Table showing my group files
 *
 * @package       mod_publication
 * @author        Andreas Hruska (andreas.hruska@tuwien.ac.at)
 * @author        Katarzyna Potocka (katarzyna.potocka@tuwien.ac.at)
 * @author        Philipp Hager (office@phager.at)
 * @copyright     2016 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license       http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class group extends base {
    /** @var groupingid grouping id for assign's team submissions */
    protected $groupingid = 0;
        /** @var protected totalfiles amount of files in table, get's counted during formating of the rows! */
    protected $totalfiles = null;

    protected function init_sql() {
        global $DB;

        $params = array();

        $fields = "g.id, g.name AS groupname, NULL AS groupmembers, COUNT(*) AS filecount,
                   SUM(files.studentapproval) AS studentapproval, NULL AS teacherapproval, MAX(files.timecreated) AS timemodified ";

        $groups = $this->publication->get_groups($this->groupingid);
        list($sqlgroupids, $groupparams) = $DB->get_in_or_equal($groups, SQL_PARAMS_NAMED, 'group');
        $params = $params + $groupparams + array('publication' => $this->cm->instance);

        $from = '{groups} g '.
           'LEFT JOIN {publication_file} files ON g.id = files.userid AND files.publication = :publication ';

        $where = "g.id ".$sqlgroupids;
        $groupby = ' g.id ';

        $this->set_sql($fields, $from, $where, $params, $groupby);
        $this->set_count_sql("SELECT COUNT(g.id) FROM ".$from." WHERE ".$where, $params);

    }

    public function __construct($uniqueid, \publication $publication) {
        global $DB, $PAGE;

        $assignid = $publication->get_instance()->importfrom;
        $this->groupingid = $DB->get_field('assign', 'teamsubmissiongroupingid', array('id' => $assignid));

        parent::__construct($uniqueid, $publication);

        $this->sortable(true, 'groupname'); // Sorted by group by default.

        // Init JS!
        $params = new \stdClass();
        $params->id = $uniqueid;
        switch($publication->get_instance()->groupapproval) {
            case PUBLICATION_APPROVAL_ALL;
                $params->mode = get_string('groupapprovalmode_all', 'mod_publication');
                break;
            case PUBLICATION_APPROVAL_SINGLE:
                $params->mode = get_string('groupapprovalmode_single', 'mod_publication');
                break;
        }

        $PAGE->requires->js_call_amd('mod_publication/groupapprovalstatus', 'initializer', array($params));
    }

    protected function get_columns() {
        $selectallnone = \html_writer::checkbox('selectallnone', false, false, '', array('id'      => 'selectallnone',
                                                                                         'onClick' => 'toggle_userselection()'));

        $columns = array('selection', 'groupname', 'groupmembers', 'timemodified');
        $headers = array($selectallnone, get_string('group'), get_string('groupmembers'), get_string('lastmodified'));
        $helpicons = array(null, null, null, null);

        if (has_capability('mod/publication:approve', $this->context)) {
            if ($this->publication->get_instance()->obtainstudentapproval) {
                $columns[] = 'studentapproval';
                $headers[] = get_string('studentapproval', 'publication');
                $helpicons[] = new \help_icon('studentapproval', 'publication');
            }
            $columns[] = 'teacherapproval';
            if ($this->publication->get_instance()->obtainstudentapproval) {
                $headers[] = get_string('obtainstudentapproval', 'publication');
            } else {
                $headers[] = get_string('teacherapproval', 'publication');
            }
            $helpicons[] = null;

            $columns[] = 'visibleforstudents';
            $headers[] = get_string('visibleforstudents', 'publication');
            $helpicons[] = null;
        }

        // Import and upload tables will enhance this list! Import from teamassignments will overwrite it!
        return array($columns, $headers, $helpicons);
    }

    /**
     * Display members of the group
     *
     * @param object $values Contains object with all the values of record.
     * @return $string Return groups members.
     */
    public function col_groupmembers($values) {
        $groupmembers = groups_get_members($values->id);
        $cell = '';

        if (!count($groupmembers)) {
            return $cell;
        }

        foreach ($groupmembers as $cur) {
            $cell .= \html_writer::tag('div', parent::col_fullname($cur));
        }

        return $cell;
    }

    /**
     * Method wraps string with span-element including data attributes containing detailed group approval data!
     *
     * @param string $symbol string/html-snippet to wrap element around
     * @param \stored_file $file file to fetch details for
     */
    protected function add_details_tooltip(&$symbol, \stored_file $file) {
        global $DB, $OUTPUT;

        $pubfileid = $DB->get_field('publication_file', 'id', array('publication' => $this->publication->get_instance()->id,
                                                                    'fileid'      => $file->get_id()));
        list(, $approvaldetails) = $this->publication->group_approval($pubfileid);

        $approved = array();
        $rejected = array();
        $pending = array();
        foreach ($approvaldetails as $cur) {
            if (empty($cur->approvaltime)) {
                $cur->approvaltime = '-';
            } else {
                $cur->approvaltime = userdate($cur->approvaltime, get_string('strftimedatetime'));
            }
            if ($cur->approval === null) {
                $pending[] = array('name' => fullname($cur), 'time' => '-');;
            } else if ($cur->approval == 0) {
                $rejected[] = array('name' => fullname($cur), 'time' => $cur->approvaltime);
            } else if ($cur->approval == 1) {
                $approved[] = array('name' => fullname($cur), 'time' => $cur->approvaltime);
            }
        }

        $status = new \stdClass();
        $status->approved = false;
        $status->rejected = false;
        $status->pending = false;
        switch ($this->publication->student_approval($file)) {
            case 2:
                $status->approved = true;
                break;
            case 1:
                $status->rejected = true;
                break;
            default:
                $status->pending = true;
        }

        $detailsattr = array('class'         => 'approvaldetails',
                             'data-pending'  => json_encode($pending),
                             'data-approved' => json_encode($approved),
                             'data-rejected' => json_encode($rejected),
                             'data-filename' => $file->get_filename(),
                             'data-status'   => json_encode($status));

        $symbol = $symbol.\html_writer::tag('span', $OUTPUT->pix_icon('i/preview', get_string('show_details', 'publication')),
                                            $detailsattr);

    }
}