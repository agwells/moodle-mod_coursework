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
/**
 * Class assessorfeedback_cell
 */
class assessorfeedback_cell extends cell_base{

    /**
     * @param submission$submission
     * @param $student
     * @param $stage_identifier
     * @return string
     */

    public function get_cell($submission, $student, $stage_identifier) {

        global $USER;

        $grade = $submission->get_assessor_feedback_by_stage($stage_identifier);
        if ($grade) {
            // check if user can see initial grades before all of them are completed
            $ability = new ability(user::find($USER), $this->coursework);

            $feedback_params = array(
                'submissionid' => $submission->id,
                'stage_identifier' => $stage_identifier,
            );
            $feedback = feedback::find($feedback_params);

            if ($submission->get_agreed_grade() || $ability->can('show', $feedback) || is_siteadmin($USER->id)) {
                $grade = strip_tags($grade->feedbackcomment);
            } else {
                $grade = '';

            }
        } else {
            $grade = '';
        }

        return $grade;
    }

    /**
     * @param $stage
     * @return string
     * @throws \coding_exception
     */
    public function get_header($stage) {
        return  get_string('assessorfeedbackcsv', 'coursework', $stage);
    }

    public function validate_cell($value, $submissionid, $stage_identifier='', $uploadedgradecells  = []) {
        global $DB, $PAGE, $USER;

        $agreedgradecap = array('mod/coursework:addagreedgrade', 'mod/coursework:editagreedgrade');
        $initialgradecap = array('mod/coursework:addinitialgrade', 'mod/coursework:editinitialgrade');

        $subdbrecord = $DB->get_record('coursework_submissions', array('id' => $submissionid));
        $submission = \mod_coursework\models\submission::find($subdbrecord);
        if (has_any_capability($agreedgradecap, $PAGE->context) && has_any_capability($initialgradecap, $PAGE->context)
            || has_capability('mod/coursework:administergrades', $PAGE->context)) {

            // Is the submission in question ready to grade?
            if (!$submission->ready_to_grade()) return get_string('submissionnotreadytograde', 'coursework');

            // Has the submission been published if yes then no further grades are allowed
            if ($submission->get_state() >= submission::PUBLISHED)  return $submission->get_status_text();

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
                if (!has_capability('mod/coursework:administergrades', $PAGE->context) && !$ability->can('new', $feedback))   return get_string('nopermissiontoeditgrade', 'coursework');

            } else {

                // This is a new feedback check it against the edit ability checks
                if (!has_capability('mod/coursework:administergrades', $PAGE->context) && !$ability->can('edit', $feedback))   return get_string('nopermissiontoeditgrade', 'coursework');

            }

            if (!$this->coursework->allocation_enabled() && !empty($feedback)) {
               // Was this user the one who last graded this submission if not then user cannot grade
                if ($feedback->assessorid != $USER->id || !has_capability('mod/coursework:editinitialgrade', $PAGE->context) )
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
                    && !$DB->get_record('coursework_allocation_pairs', $allocation_params)) return get_string('nopermissiontogradesubmission', 'coursework');
            }

            // Check for coursework without allocations - with/without samplings
            if (has_capability('mod/coursework:addinitialgrade', $PAGE->context) && !has_capability('mod/coursework:editinitialgrade', $PAGE->context)
                && $this->coursework->get_max_markers() > 1 && !$this->coursework->allocation_enabled()) {

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

        }  else if (has_any_capability($agreedgradecap, $PAGE->context)) {

            // If you have the add agreed or edit agreed grades capabilities then you may have the grades on your export sheet
            // We will return true as we will ignore them
            return true;

        } else {
            return get_string('nopermissiontoimportgrade', 'coursework');
        }
    }

}
