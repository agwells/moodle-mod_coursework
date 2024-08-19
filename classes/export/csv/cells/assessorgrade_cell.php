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
 * @package    mod_coursework
 * @copyright  2017 University of London Computer Centre {@link ulcc.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_coursework\export\csv\cells;
use mod_coursework\models\submission;
use mod_coursework\ability;
use mod_coursework\models\user;
use mod_coursework\models\feedback;
use mod_coursework\grade_judge;
use mod_coursework\export;
/**
 * Class assessorgrade_cell
 */
class assessorgrade_cell extends cell_base {

    /**
     * @param submission$submission
     * @param $student
     * @param $stage_identifier
     * @return string
     */

    public function get_cell($submission, $student, $stage_identifier) {

        global $USER;

        $grade = $submission->get_assessor_feedback_by_stage($stage_identifier);

        // check if user can see initial grades before all of them are completed
        $ability = new ability(user::find($USER), $this->coursework);

        $feedback_params = array(
            'submissionid' => $submission->id,
            'stage_identifier' => $stage_identifier,
        );
        $feedback = feedback::find($feedback_params);

        if (($submission->get_agreed_grade() || ($feedback && $ability->can('show', $feedback))) || !$submission->any_editable_feedback_exists() || is_siteadmin($USER->id)) {

            if ($this->coursework->is_using_rubric()) {
                $gradedata = [];
                $this->get_rubric_scores_gradedata($grade, $gradedata); // multiple parts are handled here
            } else {
                $gradedata = (!$grade) ? '' : $this->get_actual_grade($grade->grade);

            }

        } else {

            if ($this->coursework->is_using_rubric()) {
                $criterias = $this->coursework->get_rubric_criteria();
                foreach ($criterias as $criteria) { // rubrics can have multiple parts, so let's create header for each of it
                    $gradedata['assessor'.$stage_identifier.'_'.$criteria['id']] = get_string('grade_hidden_manager', 'mod_coursework');
                    $gradedata['assessor'.$stage_identifier.'_'.$criteria['id']. 'comment'] = '';
                }
            } else {
                $gradedata = get_string('grade_hidden_manager', 'mod_coursework');
            }

        }

        return $gradedata;
    }

    /**
     * @param $stage
     * @return string
     * @throws \coding_exception
     */
    public function get_header($stage) {

        if ($this->coursework->is_using_rubric() ) {
            $strings = [];
            $criterias = $this->coursework->get_rubric_criteria();
            foreach ($criterias as $criteria) { // rubrics can have multiple parts, so let's create header for each of it
                $strings['assessorgrade'.$stage.'_'.$criteria['id']] = 'Assessor '.$stage.' - '.$criteria['description'];
                $strings['assessorgrade'.$stage.'_'.$criteria['id'] . 'comment'] = 'Comment for:  Assessor '.$stage.' - '.$criteria['description'];
            }
        } else {
            $strings = get_string('assessorgradecsv', 'coursework', $stage);
        }

        return  $strings;

    }

    public function validate_cell($value, $submissionid, $stage_identifier='', $uploadedgradecells  = []) {
        global $DB, $PAGE, $USER;

        if (empty($value)) return true;

        $agreedgradecap = array('mod/coursework:addagreedgrade', 'mod/coursework:editagreedgrade');
        $initialgradecap = array('mod/coursework:addinitialgrade', 'mod/coursework:editinitialgrade');
        $subdbrecord = $DB->get_record('coursework_submissions', array('id' => $submissionid));
        $submission = \mod_coursework\models\submission::find($subdbrecord);

        if (has_any_capability($agreedgradecap, $PAGE->context) && has_any_capability($initialgradecap, $PAGE->context)
            || has_capability('mod/coursework:administergrades', $PAGE->context)
        ) {

            $errormsg = '';

            if (!$this->coursework->is_using_rubric()) {
                $gradejudge = new grade_judge($this->coursework);
                if (!$gradejudge->grade_in_scale($value)) {
                    $errormsg = get_string('valuenotincourseworkscale', 'coursework');
                    if (is_numeric($value)) {
                        // if scale is numeric get max allowed scale
                        $errormsg .= ' '. get_string('max_cw_mark', 'coursework').' '. $this->coursework->grade;
                    }
                }
            } else {

                // We won't be processing this line if it has no values, empty wont tell us this as it thinks that an array with
                // Keys isnt. We will use array_filter whhich will return all values from the array if this is empty then we have
                // Nothing to do

                $arrayvalues = array_filter($value);

                // If there are no values we don't need to do anything
                if (!empty($arrayvalues)) {

                    $i = 0;
                    $s = 0;

                    $criterias = $this->coursework->get_rubric_criteria();

                    foreach ($value as $data) {

                        // Check if the value is empty however it can be 0
                        if (empty($data) && $data != 0) {

                            $errormsg .= ' ' . get_string('rubric_grade_cannot_be_empty', 'coursework');

                        }

                        // Only check grades fields that will be even numbered
                        if ($i % 2 == 0) {

                            // Get the current criteria
                            $criteria = array_shift($criterias);

                            //lets check if the value given is valid for the current rubric criteria
                            if (!$this->value_in_rubric($criteria, $data)) {
                                // if scale is numeric get max allowed scale
                                $errormsg .= ' ' . get_string('rubric_invalid_value', 'coursework') . ' ' . $data;
                            }
                            $s++;
                        }
                        $i++;
                    }
                }

            }

            if (!empty($errormsg))  return $errormsg;

            // Is the submission in question ready to grade?
            if (!$submission->ready_to_grade()) return get_string('submissionnotreadytograde', 'coursework');

            // Has the submission been published if yes then no further grades are allowed
            if ($submission->get_state() >= submission::PUBLISHED) return $submission->get_status_text();

            // If you have administer grades you can grade anything
            if (has_capability('mod/coursework:administergrades', $PAGE->context)) return true;

            // Has this submission been graded if yes then check if the current user graded it (only if allocation is not enabled).
            $feedback_params = array(
                'submissionid' => $submission->id,
                'stage_identifier' => $stage_identifier,
            );
            $feedback = feedback::find($feedback_params);

            $ability = new ability(user::find($USER), $this->coursework);

            //does a feedback exist for this stage
            if (!empty($feedback)) {
                // This is a new feedback check it against the new ability checks
                if (!has_capability('mod/coursework:administergrades', $PAGE->context) && !$ability->can('new', $feedback)) return get_string('nopermissiontoeditgrade', 'coursework');

            } else {

                // This is a new feedback check it against the edit ability checks
                if (!has_capability('mod/coursework:administergrades', $PAGE->context) && !$ability->can('edit', $feedback)) return get_string('nopermissiontoeditgrade', 'coursework');

            }

            if (!$this->coursework->allocation_enabled() && !empty($feedback)) {
               // Was this user the one who last graded this submission if not then user cannot grade
                if ($feedback->assessorid != $USER->id || !has_capability('mod/coursework:editinitialgrade', $PAGE->context))
                    return get_string('nopermissiontogradesubmission', 'coursework');

            }

            if ($this->coursework->allocation_enabled()) {
                // Check that the user is allocated to the author of the submission
                $allocation_params = array(
                    'courseworkid' => $this->coursework->id,
                    'allocatableid' => $submission->allocatableid,
                    'allocatabletype' => $submission->allocatabletype,
                    'stage_identifier' => $stage_identifier
                );

                if (!has_capability('mod/coursework:administergrades', $PAGE->context)
                    && !$DB->get_record('coursework_allocation_pairs', $allocation_params)
                ) return get_string('nopermissiontogradesubmission', 'coursework');
            }

            // Check for coursework without allocations - with/without samplings
            if (has_capability('mod/coursework:addinitialgrade', $PAGE->context) && !has_capability('mod/coursework:editinitialgrade', $PAGE->context)
                && $this->coursework->get_max_markers() > 1 && !$this->coursework->allocation_enabled()
            ) {

                // check how many feedbacks for this submission
                $feedbacks = $DB->count_records('coursework_feedbacks', array('submissionid' => $submissionid));

                if ($this->coursework->sampling_enabled()) {
                    // check how many sample assessors + add 1 that is always in sample
                    $in_sample = $submission->get_submissions_in_sample();
                    $assessors = ($in_sample) ? count($in_sample) + 1 : 1;
                } else {
                    // Check how many assessors for this coursework
                    $assessors = $this->coursework->get_max_markers();
                }
                if ($assessors == $feedbacks) return get_string('gradealreadyexists', 'coursework');
            }

        } else if (has_any_capability($agreedgradecap, $PAGE->context)) {

          // If you have the add agreed or edit agreed grades capabilities then you may have the grades on your export sheet
          // We will return true as we will ignore them
          return true;

        } else {
            return get_string('nopermissiontoimportgrade', 'coursework');
        }
    }

    /***
     * Check that the given value is within the values that can be excepted by the given rubric criteria
     *
     * @param $criteria the criteria array, this must contain the levels element
     * @param $value the value that should be checked to see if it is valid
     * @return bool
     */
    function value_in_rubric($criteria,    $value) {

        global  $DB;

        $valuefound = false;

        $levels = $criteria['levels'];

        if (is_numeric($value) ) {
            foreach ($levels as $level) {

                if ((int)$level['score'] == (int)$value) {

                    $valuefound = true;
                    break;
                }

            }
        }

        return $valuefound;
    }

    /**
     * Takes the given cells and returns the cells with the singlegrade cell replaced by the rubric headers if the coursework instance
     * makes use of rubrics
     *
     * @param $csv_cells
     *
     */
    function get_rubrics($coursework, $csv_cells) {

        if ($coursework->is_using_rubric()) {

            $rubricheaders = [];

            $criterias = $coursework->get_rubric_criteria();

            foreach ($criterias as $criteria) {
                $rubricheaders[] = $criteria['description'];
                $rubricheaders[] = $criteria['description']." comment";
            }

            // Find out the position of singlegrade
            $position = array_search('singlegrade', $csv_cells);
            // Get all data from the position of the singlegrade to the length of rubricheaders
            // $csv_cells = array_splice($csv_cells,5, 1, $rubricheaders);

            $start_cells = array_slice($csv_cells, 0, $position, true);
            $end_cells = array_slice($csv_cells, $position + 1, count($csv_cells), true);

            $cells = array_merge($start_cells, $rubricheaders);

            $cells = array_merge($cells, $end_cells);

        }

        return $cells;
    }

}
