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

use mod_coursework\allocation\allocatable;
use mod_coursework\models\coursework;
use mod_coursework\models\deadline_extension;
use mod_coursework\models\feedback;
use mod_coursework\models\group;
use mod_coursework\models\submission;
use mod_coursework\models\user;

use Behat\Mink\Exception\ElementNotFoundException;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/mod/coursework/tests/behat/pages/single_grading_interface.php');

/**
 * Holds the functions that know about the HTML structure of the multiple grading page.
 *
 */
class mod_coursework_behat_multiple_grading_interface extends mod_coursework_behat_single_grading_interface {

    /**
     * @param allocatable $allocatable
     * @return bool is there an icon?
     */
    public function there_is_a_feedback_icon($allocatable): bool {
        $this->getContext()->show_me_the_page();
        $feedback_icon = $this->getPage()->findAll('css', '.cfeedbackcomment .smallicon');
        return !empty($feedback_icon);
    }

    /**
     * @param user $allocatable
     */
    public function student_has_a_final_grade($allocatable): bool {
        $student_grade_cell =
            $this->getPage()->find('css', $this->allocatable_row_id($allocatable) . ' .multiple_agreed_grade_cell');
        return !empty($student_grade_cell->getText());
    }

    /**
     * @param group $allocatable
     */
    public function group_has_a_final_multiple_grade($allocatable): bool {
        $group_row_id = $this->allocatable_row_id($allocatable);
        $locator = $group_row_id . ' .multiple_agreed_grade_cell';
        $grade_cell = $this->getPage()->find('css', $locator);
        $actual_text = $grade_cell ? $grade_cell->getText() : '';
        return !empty($actual_text);
    }

    /**
     * @param allocatable $allocatable
     */
    public function click_new_final_feedback_button($allocatable) {
        $identifier = '#new_final_feedback_'. $this->allocatable_identifier_hash($allocatable);
        $nodeElement = $this->getPage()->find('css', $identifier);
        if ($nodeElement) {
            $nodeElement->click();
        }
    }

    /**
     * @param allocatable $allocatable
     */
    public function get_edit_final_feedback_button($allocatable) {
        $identifier = '#edit_final_feedback_' . $this->allocatable_identifier_hash($allocatable);
        return $this->getPage()->find('css', $identifier);
    }

    /**
     * @param allocatable $allocatable
     * @return string
     */
    private function allocatable_row_id($allocatable) {
        return '#allocatable_' . $this->allocatable_identifier_hash($allocatable);
    }

    /**
     * @param allocatable $allocatable
     * @return string
     */
    private function assessor_feedback_table_id($allocatable) {
        return '#assessorfeedbacktable_' . $this->allocatable_identifier_hash($allocatable);
    }

    /**
     * @param allocatable $allocatable
     * @throws Behat\Mink\Exception\ElementException
     */
    public function click_new_moderator_feedback_button($allocatable) {
        $identifier = $this->allocatable_row_id($allocatable).' .moderation_cell .new_feedback';
        $this->getPage()->find('css', $identifier)->click();
    }

    /**
     * @param allocatable $student
     * @param $grade
     */
    public function has_moderator_grade_for($student, $grade) {
        $identifier = $this->allocatable_row_id($student) . ' .moderation_cell';
        $text = $this->getPage()->find('css', $identifier)->getText();
        return str_contains($text, $grade);
    }

    /**
     * @param allocatable $student
     * @throws Behat\Mink\Exception\ElementException
     */
    public function click_edit_moderator_feedback_button($student) {
        $identifier = $this->allocatable_row_id($student) . ' .moderation_cell .edit_feedback';
        $this->getPage()->find('css', $identifier)->click();
    }

    /**
     * @param allocatable $allocatable
     * @param int $assessor_number
     * @param int $expected_grade
     */
    public function assessor_grade_should_be_present($allocatable, $assessor_number, $expected_grade) {
        $locator = $this->assessor_feedback_table_id($allocatable) . ' .assessor_'.$assessor_number.' '. $this->assessor_grade_cell_class();
        $grade_container = $this->getPage()->find('css', $locator);
        $text = $grade_container ? $grade_container->getText() : '';
        if (!str_contains($text, (string)$expected_grade)) {
            throw new \Behat\Mink\Exception\ExpectationException(
                "Did not find expected grade '$expected_grade' in '$text'",
                $this->getSession()
            );
        }
    }

    /**
     * @param allocatable $allocatable
     * @param int $assessor_number
     * @param int $expected_grade
     */
    public function assessor_grade_should_not_be_present($allocatable, $assessor_number, $expected_grade) {
        $locator =
            $this->assessor_feedback_table_id($allocatable) . ' .assessor_' . $assessor_number . ' ' . $this->assessor_grade_cell_class();
        $cell = $this->getPage()->findAll('css', $locator);
        if (!empty($cell)) {
            $cell = reset($cell);
            $text = $cell ? $cell->getText() : '';
            if (str_contains($text, $expected_grade)) {
                throw new \Behat\Mink\Exception\ExpectationException(
                    "Expected not to find grade '$expected_grade' but did find it in '$text'",
                    $this->getSession()
                );
            }
        }
    }

    /**
     * @return string
     */
    protected function assessor_grade_cell_class() {
        return '.assessor_feedback_grade';
    }

    /**
     * @param int $assessor_number
     * @param allocatable $allocatable
     */
    public function click_assessor_new_feedback_button($assessor_number, $allocatable) {
        $locator =
            $this->assessor_feedback_table_id($allocatable) . ' .assessor_' . $assessor_number . ' ' . $this->assessor_grade_cell_class().' .new_feedback';
        $this->click_that_thing($locator);
    }

    public function press_publish_button() {
        $this->getPage()->pressButton('id_publishbutton');
    }

    public function confirm_publish_action() {

        if ($this->getPage()->hasButton('Continue')) {
            $this->getPage()->pressButton('Continue');
        } else {
            echo "failed";
        }

        if ($this->getPage()->hasLink('Continue')) {
            $this->getPage()->clickLink('Continue');
        } else {

        }
    }

    /**
     * @param feedback $feedback
     * @throws \Behat\Mink\Exception\ElementNotFoundException
     */
    public function click_feedback_show_icon($feedback) {
        $link_id = "show_feedback_" . $feedback->id;
        $this->getPage()->clickLink($link_id);

        if ($this->getPage()->hasLink('Continue')) {
            $this->getPage()->clickLink('Continue');
        }
    }

    /**
     * @param feedback $feedback
     */
    public function should_not_have_show_feedback_icon($feedback) {
        $link_id = "show_feedback_" . $feedback->id;
        $this->should_not_have_css($link_id);
    }

    /**
     * @param feedback $feedback
     */
    public function should_not_have_grade_in_assessor_table($feedback) {
        $assessor = $feedback->assessor();
        $row_class = '.feedback-' . $assessor->id() . '-' . $feedback->get_allocatable()
            ->id() . '.' . $feedback->get_stage()->identifier().' .assessor_feedback_grade';

        $this->should_not_have_css($row_class, $feedback->grade);
    }

    /**
     * @param feedback $feedback
     */
    public function should_have_grade_in_assessor_table($feedback) {
        $assessor = $feedback->assessor();
        $row_class = '.feedback-' . $assessor->id() . '-' . $feedback->get_allocatable()
            ->id() . '.' . $feedback->get_stage()->identifier() . ' .assessor_feedback_grade';

        $this->should_have_css($row_class, $feedback->grade);
    }

    /**
     * @param feedback $feedback
     */
    public function should_not_have_edit_link_for_feedback($feedback) {
        $identifier = '#edit_feedback_'.$feedback->id;
        $this->should_not_have_css($identifier);
    }

    /**
     * @param feedback $feedback
     */
    public function should_have_edit_link_for_feedback($feedback) {
        $identifier = '#edit_feedback_'.$feedback->id;
        $this->should_have_css($identifier);
    }

    /**
     * @param $allocatable
     */
    public function should_not_have_add_button_for_final_feedback($studentid) {
        $identifier = '#new_final_feedback_'.$studentid;
        $this->should_not_have_css($identifier);
    }

    /**
     * @param $allocatable
     */
    public function should_have_add_button_for_final_feedback($studentid) {
        $identifier = '#new_final_feedback_'.$studentid;
        $this->should_have_css($identifier);
    }

    /**
     * @param feedback $feedback
     */
    public function should_not_have_edit_link_for_final_feedback($allocatable) {
        $identifier = '#edit_final_feedback_' . $this->allocatable_identifier_hash($allocatable);
        $this->should_not_have_css($identifier);
    }

    /**
     * @param submission $submission
     */
    public function should_not_have_new_feedback_button($submission) {
        $elementid = $this->new_feedback_button_css($submission);
        $this->should_not_have_css($elementid);
    }

    /**
     * @param submission $submission
     */
    public function should_have_new_feedback_button($submission) {
        $elementid = $this->new_feedback_button_css($submission);
        echo $elementid;
        $this->should_have_css($elementid);
    }

    /**
     * @param submission $submission
     * @return string
     */
    protected function new_feedback_button_css($submission) {
        $elementid = '#assessorfeedbacktable_' . $submission->get_coursework()
            ->get_allocatable_identifier_hash($submission->get_allocatable()). ' .new_feedback';
        return $elementid;
    }

    /**
     * @param submission $submission
     * @return string
     */
    public function get_provisional_grade_field($submission) {
        $elementid = '#allocatable_' . $submission->get_coursework()
            ->get_allocatable_identifier_hash($submission->get_allocatable()). ' .assessor_feedback_grade';
        $grade_field = $this->getPage()->find('css', $elementid);
        return $grade_field ? $grade_field->getValue() : false;
    }

    /**
     * @param submission $submission
     * @return string
     */
    public function get_grade_field($submission) {
        $elementid = '#assessorfeedbacktable_' . $submission->get_coursework()
            ->get_allocatable_identifier_hash($submission->get_allocatable()). ' .grade_for_gradebook_cell';
        $grade_field = $this->getPage()->find('css', $elementid);
        return $grade_field ? $grade_field->getValue() : false;
    }

    /**
     * @param user $student
     * @throws \Behat\Mink\Exception\ElementException
     */
    public function click_new_extension_button_for($student) {
        $element_selector = $this->allocatable_row_id($student).' .new_deadline_extension';
        $this->getPage()->find('css', $element_selector)->click();
    }

    /**
     * @param user $student
     * @throws \Behat\Mink\Exception\ElementException
     */
    public function click_edit_extension_button_for($student) {
        $element_selector = $this->allocatable_row_id($student) . ' .edit_deadline_extension';
        $this->getPage()->find('css', $element_selector)->click();
    }

    /**
     * @param allocatable $student
     * @param int $deadline_extension
     */
    public function should_show_extension_for_allocatable($student, $deadline_extension) {
        $element_selector = $this->allocatable_row_id($student).' .time_submitted_cell';
        $this->should_have_css($element_selector, userdate($deadline_extension, '%a, %d %b %Y, %H:%M' ));
    }

    /**
     * @param allocatable $allocatable
     * @throws \Behat\Mink\Exception\ElementException
     */
    public function click_new_submission_button_for($allocatable) {
        $element_selector = $this->allocatable_row_id($allocatable) . ' .new_submission';
        $this->getPage()->find('css', $element_selector)->click();
    }

    /**
     * @param allocatable $allocatable
     * @throws \Behat\Mink\Exception\ElementException
     */
    public function click_edit_submission_button_for($allocatable) {
        $element_selector = $this->allocatable_row_id($allocatable) . ' .edit_submission';
        $this->getPage()->find('css', $element_selector)->click();
    }
}
