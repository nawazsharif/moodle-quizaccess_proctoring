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
 * Implementaton for the quizaccess_proctoring plugin.
 *
 * @package    quizaccess_proctoring
 * @copyright  2020 Brain Station 23
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/quiz/accessrule/accessrulebase.php');


/**
 * quizaccess_proctoring
 */
class quizaccess_proctoring extends quiz_access_rule_base
{

    /**
     * Check is preflight check is required.
     *
     * @param mixed $attemptid
     * @return bool
     */
    public function is_preflight_check_required($attemptid) {
        $script = $this->get_topmost_script();
        $base = basename($script);
        if($base == "view.php"){
            return true;
        }
        else{
            return false;
        }
    }

    /**
     * Get topmost script path
     *
     * @return String
     * @throws coding_exception
     */
    public function get_topmost_script() {
        $backtrace = debug_backtrace(
            defined("DEBUG_BACKTRACE_IGNORE_ARGS")
                ? DEBUG_BACKTRACE_IGNORE_ARGS
                : FALSE);
        $top_frame = array_pop($backtrace);
        return $top_frame['file'];
    }

    /**
     * Get_courseid_cmid_from_preflight_form
     *
     * @param mod_quiz_preflight_check_form $quizform
     * @return array
     * @throws coding_exception
     */
    public function get_courseid_cmid_from_preflight_form(mod_quiz_preflight_check_form $quizform){
        $response = array();
        $response['courseid'] = $this->quiz->course;
        $response['quizid'] = $this->quiz->id;
        $response['cmid'] = $this->quiz->cmid;
        return $response;
    }

    public function make_modal_content($quizform){
        global $USER,$OUTPUT;
        $headercontent = get_string('openwebcam', 'quizaccess_proctoring');
        $header = "<h3>$headercontent</h3>";

        $camhtml = get_string('camhtml', 'quizaccess_proctoring');
        $screenhtml = get_string('screenhtml', 'quizaccess_proctoring');
        $proctoringstatement = get_string('proctoringstatement', 'quizaccess_proctoring');
        $screensharemsg = get_string('screensharemsg', 'quizaccess_proctoring');
        $html = "<div style='margin: auto !important;padding: 30px !important;'>
                 <table>
                    <tr>
                        <td colspan='2'>$header</td>
                    </tr>
                    <tr>
                        <td colspan='2'>$proctoringstatement</td>
                    </tr>
                    <tr>
                        <td colspan='2'>$screensharemsg</td>
                    </tr>
                    <tr>
                        <td>$camhtml</td>
                        <td>$screenhtml</td>
                    </tr>   
                </table></div>";

        return $html;
    }


    /**
     * add_preflight_check_form_fields
     *
     * @param mod_quiz_preflight_check_form $quizform
     * @param MoodleQuickForm $mform
     * @param mixed $attemptid
     * @return void
     * @throws coding_exception
     */
    public function add_preflight_check_form_fields(mod_quiz_preflight_check_form $quizform, MoodleQuickForm $mform, $attemptid) {
        global $PAGE,$DB,$USER;
        $coursedata = $this->get_courseid_cmid_from_preflight_form($quizform);
        // Get Screenshot Delay and Image Width.
        $imagedelaysql = "SELECT * FROM {config_plugins}
                        WHERE plugin = 'quizaccess_proctoring'
                        AND name = 'autoreconfigurecamshotdelay'";
        $delaydata = $DB->get_record_sql($imagedelaysql);

        $camshotdelay = (int)$delaydata->value * 1000;
        if($camshotdelay == 0){
            $camshotdelay = 30 * 1000;
        }

        $faceidquery = "SELECT * FROM {config_plugins}
                        WHERE plugin = 'quizaccess_proctoring'
                        AND name = 'fcheckstart'";
        $faceidrow = $DB->get_record_sql($faceidquery);
        $faceidcheck = $faceidrow->value;


        $record = array();
        $record["id"] = 0;
        $record["courseid"] = (int)$coursedata['courseid'];
        $record["cmid"] = (int)$coursedata['cmid'];
        $record["screenshotinterval"] = $camshotdelay;

        $PAGE->requires->js_call_amd('quizaccess_proctoring/startAttempt', 'setup', array($record));
        $attributesarray = $mform->_attributes;
        $attributesarray['target'] = '_blank';
        $mform->_attributes = $attributesarray;


        $profileimageurl = "";
        if ($USER->picture) {
            $profileimageurl = new moodle_url('/user/pix.php/'.$USER->id.'/f1.jpg');//get_file_url($user->id.'/'.$size['large'].'.jpg', null, 'user');
        }
        $coursedata = $this->get_courseid_cmid_from_preflight_form($quizform);
        $hiddenvalue = "<input id='window_surface' value='' type='hidden'/>
                        <input id='share_state' value='' type='hidden'/>
                        <input id='screen_off_flag' value='0' type='hidden'/>".
                        '<input type="hidden" id="courseidval" value="'.$coursedata['courseid'].'"/>
                        <input type="hidden" id="cmidval" value="'.$coursedata['cmid'].'"/>
                        <input type="hidden" id="profileimage" value="'.$profileimageurl.'"/>';


        $modalcontent = $this->make_modal_content($quizform);
        $css = "<style>
                    .moodle-dialogue{
                        width: 900px !important;
                    }
                    .loadingspinner {
                        pointer-events: none;
                        width: 1.5em;
                        height: 1.5em;
                        border: 0.4em solid transparent;
                        border-color: #eee;
                        border-top-color: #3E67EC;
                        border-radius: 50%;
                        animation: loadingspin 1s linear 	infinite;
                        display: none;
                    }
                    
                    @keyframes loadingspin {
                        100% {
                                transform: rotate(360deg)
                        }
                    }
                </style>";
        if($faceidcheck == "yes"){
            $actionbtns = "<button id='share_screen_btn' style='margin: 5px;display: none'>share screen</button>
                       <button id='fcvalidate' style='height:50px; margin: 5px; display: flex; justify-content: center;align-items: center;'><div class='loadingspinner' id='loading_spinner'></div>Validate Face Recognition</button>";
        }
        else{
            $actionbtns = "<button id='share_screen_btn' style='margin: 5px;'>share screen</button>";
        }

        $mform->addElement('html', $modalcontent);
        $mform->addElement('static', 'actionbtns', '', $actionbtns);
        $mform->addElement('html', '<div id="form_activate" style="visibility: hidden">');
        $mform->addElement('checkbox', 'proctoring', '', get_string('proctoringlabel', 'quizaccess_proctoring'));
        $mform->addElement('html', '</div>');
        $mform->addElement('html', $hiddenvalue);
        $mform->addElement('html', $css);
    }

    /**
     * Validate the preflight check
     *
     * @param mixed $data
     * @param mixed $files
     * @param mixed $errors
     * @param mixed $attemptid
     * @return mixed $errors
     * @throws coding_exception
     */
    public function validate_preflight_check($data, $files, $errors, $attemptid) {
        if (empty($data['proctoring'])) {
            $errors['proctoring'] = get_string('youmustagree', 'quizaccess_proctoring');
        }

        return $errors;
    }

    /**
     * * Information, such as might be shown on the quiz view page, relating to this restriction.
     * There is no obligation to return anything. If it is not appropriate to tell students
     * about this rule, then just return ''.
     *
     * @param quiz $quizobj
     * @param int $timenow
     * @param bool $canignoretimelimits
     * @return quiz_access_rule_base|quizaccess_proctoring|null
     */
    public static function make(quiz $quizobj, $timenow, $canignoretimelimits) {
        if (empty($quizobj->get_quiz()->proctoringrequired)) {
            return null;
        }
        return new self($quizobj, $timenow);
    }

    /**
     * Add any fields that this rule requires to the quiz settings form. This
     * method is called from mod_quiz_mod_form::definition(), while the
     * security section is being built.
     *
     * @param mod_quiz_mod_form $quizform the quiz settings form that is being built.
     * @param MoodleQuickForm $mform the wrapped MoodleQuickForm.
     * @throws coding_exception
     */
    public static function add_settings_form_fields(mod_quiz_mod_form $quizform, MoodleQuickForm $mform) {
        $mform->addElement('select', 'proctoringrequired',
            get_string('proctoringrequired', 'quizaccess_proctoring'),
            array(
                0 => get_string('notrequired', 'quizaccess_proctoring'),
                1 => get_string('proctoringrequiredoption', 'quizaccess_proctoring'),
            ));
        $mform->addHelpButton('proctoringrequired', 'proctoringrequired', 'quizaccess_proctoring');
    }

    /**
     * Save any submitted settings when the quiz settings form is submitted. This
     * is called from quiz_after_add_or_update() in lib.php.
     *
     * @param object $quiz the data from the quiz form, including $quiz->id
     *      which is the id of the quiz being saved.
     * @throws dml_exception
     */
    public static function save_settings($quiz) {
        global $DB;
        if (empty($quiz->proctoringrequired)) {
            $DB->delete_records('quizaccess_proctoring', array('quizid' => $quiz->id));
        } else {
            if (!$DB->record_exists('quizaccess_proctoring', array('quizid' => $quiz->id))) {
                $record = new stdClass();
                $record->quizid = $quiz->id;
                $record->proctoringrequired = 1;
                $DB->insert_record('quizaccess_proctoring', $record);
            }
        }
    }

    /**
     * Delete any rule-specific settings when the quiz is deleted. This is called
     * from quiz_delete_instance() in lib.php.
     *
     * @param object $quiz the data from the database, including $quiz->id
     *      which is the id of the quiz being deleted.
     * @throws dml_exception
     */
    public static function delete_settings($quiz) {
        global $DB;
        $DB->delete_records('quizaccess_proctoring', array('quizid' => $quiz->id));
    }

    /**
     * Return the bits of SQL needed to load all the settings from all the access
     * plugins in one DB query. The easiest way to understand what you need to do
     * here is probalby to read the code of quiz_access_manager::load_settings().
     *
     * If you have some settings that cannot be loaded in this way, then you can
     * use the get_extra_settings() method instead, but that has
     * performance implications.
     *
     * @param int $quizid the id of the quiz we are loading settings for. This
     *     can also be accessed as quiz.id in the SQL. (quiz is a table alisas for {quiz}.)
     * @return array with three elements:
     *     1. fields: any fields to add to the select list. These should be alised
     *        if neccessary so that the field name starts the name of the plugin.
     *     2. joins: any joins (should probably be LEFT JOINS) with other tables that
     *        are needed.
     *     3. params: array of placeholder values that are needed by the SQL. You must
     *        used named placeholders, and the placeholder names should start with the
     *        plugin name, to avoid collisions.
     */
    public static function get_settings_sql($quizid) {
        return array(
            'proctoringrequired',
            'LEFT JOIN {quizaccess_proctoring} proctoring ON proctoring.quizid = quiz.id',
            array());
    }

    /**
     * Information, such as might be shown on the quiz view page, relating to this restriction.
     * There is no obligation to return anything. If it is not appropriate to tell students
     * about this rule, then just return ''.
     *
     * @return mixed a message, or array of messages, explaining the restriction
     *         (may be '' if no message is appropriate).
     * @throws coding_exception
     */
    public function description() {
        global $PAGE;
        $record = new stdClass();
        $record->allowcamerawarning = get_string('warning:cameraallowwarning', 'quizaccess_proctoring');
        $PAGE->requires->js_call_amd('quizaccess_proctoring/proctoring', 'init', array($record));
        $messages = [get_string('proctoringheader', 'quizaccess_proctoring')];

        $messages[] = $this->get_download_config_button();

        return $messages;
    }

    /**
     * Sets up the attempt (review or summary) page with any special extra
     * properties required by this rule.
     *
     * @param moodle_page $page the page object to initialise.
     * @throws coding_exception
     * @throws dml_exception
     */
    public function setup_attempt_page($page) {
        $cmid = optional_param('cmid', '', PARAM_INT);
        $attempt = optional_param('attempt', '', PARAM_INT);

        $page->set_title($this->quizobj->get_course()->shortname . ': ' . $page->title);
        $page->set_popup_notification_allowed(false); // Prevent message notifications.
        $page->set_heading($page->title);

        global $DB, $COURSE, $USER;
        if ($cmid) {
            $contextquiz = $DB->get_record('course_modules', array('id' => $cmid));

            $record = new stdClass();
            $record->courseid = $COURSE->id;
            $record->quizid = $contextquiz->id;
            $record->userid = $USER->id;
            $record->webcampicture = '';
            $record->status = $attempt;
            $record->timemodified = time();
            $record->id = $DB->insert_record('quizaccess_proctoring_logs', $record, true);

            // Get Screenshot Delay and Image Width.
            $imagedelaysql = "SELECT * FROM {config_plugins}
            WHERE plugin = 'quizaccess_proctoring' AND name = 'autoreconfigurecamshotdelay'";
            $delaydata = $DB->get_records_sql($imagedelaysql);

            $camshotdelay = 30 * 1000;
            if (count($delaydata) > 0) {
                foreach ($delaydata as $row) {
                    $camshotdelay = (int)$row->value * 1000;
                }
            }

            $imagesizesql = "SELECT * FROM {config_plugins}
            WHERE plugin = 'quizaccess_proctoring' AND name = 'autoreconfigureimagewidth'";
            $imagesizedata = $DB->get_records_sql($imagesizesql);

            $imagewidth = 230;
            if (count($imagesizedata) > 0) {
                foreach ($imagesizedata as $row) {
                    $imagewidth = (int)$row->value;
                }
            }
            $quizurl = new moodle_url("/mod/quiz/view.php",array("id"=> $cmid));
            $record->camshotdelay = $camshotdelay;
            $record->image_width = $imagewidth;
            $record->quizurl = $quizurl->__toString();
            $page->requires->js_call_amd('quizaccess_proctoring/proctoring', 'setup', array($record));
        }
    }

    /**
     * Get a button to view the Proctoring report.
     *
     * @return string A link to view report
     * @throws coding_exception
     */
    private function get_download_config_button() : string {
        global $OUTPUT, $USER;

        $context = context_module::instance($this->quiz->cmid, MUST_EXIST);
        if (has_capability('quizaccess/proctoring:viewreport', $context, $USER->id)) {
            $httplink = \quizaccess_proctoring\link_generator::get_link($this->quiz->course, $this->quiz->cmid, false, is_https());

            return $OUTPUT->single_button($httplink, get_string('picturesreport', 'quizaccess_proctoring'), 'get');
        } else {
            return '';
        }
    }

}
