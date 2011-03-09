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
 * The form for setting which categories should be shown in the block.
 *
 * @package    blocks
 * @subpackage cam_mycourses
 * @author     Dan Marsden <dan@danmarsden.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . '/formslib.php');

class mycourse_categories_form extends moodleform {

    function definition() {
        $mform =& $this->_form;

        make_categories_list(&$list, &$parents);
        $this->print_category_edit(NULL, $list, $parents);
        $this->add_action_buttons();
    }

    function print_category_edit($category, $displaylist, $parentslist, $depth=-1, $up=false, $down=false, $thisparents=array()) {
    /// Recursive function to print all the categories ready for editing
        global $CFG, $USER, $OUTPUT;
        $mform =& $this->_form;

        static $str = NULL;
        static $config = NULL;

        if (is_null($config)) {
            $config = get_config('block_cam_mycourses');
        }

        if (is_null($str)) {
            $str = new stdClass;
            $str->spacer = $OUTPUT->spacer().' ';
            $str->full = get_string('full', 'block_cam_mycourses');
            $str->listing = get_string('listing', 'block_cam_mycourses');
            $str->no = get_string('no');
            $str->display = get_string('display', 'block_cam_mycourses');
            $str->cascade = get_string('cascade', 'block_cam_mycourses');
            $str->enrol = get_string('enrolled', 'block_cam_mycourses');
        }
        $options = array(0=>$str->no, 1=>$str->full, 2=>$str->listing);
        if (!empty($category)) {

            if (!isset($category->context)) {
                $category->context = get_context_instance(CONTEXT_COURSECAT, $category->id);
            }

           // echo '<tr><td align="left" class="name">';
            $categoryprefix = '';
            for ($i=0; $i<$depth;$i++) {
                $categoryprefix .= '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
            }
            //echo '</td>';
            $display = "display_".$category->id;
            $display = isset($config->$display) ? $config->$display : 0;
            $enrolled = "enrol_".$category->id;
            $enrolled = isset($config->$enrolled) ? $config->$enrolled : false;
            $cascade = "cascade_".$category->id;
            $cascade = isset($config->$cascade) ? $config->$cascade : false;
            $mform->addElement('header', 'header'.$category->id, $categoryprefix.format_string($category->name));
            $mform->addElement('select', 'display'.$category->id, $str->display, $options);
            $mform->setDefault('display'.$category->id, $display);
            $mform->addElement('checkbox', 'cascade'.$category->id, $str->cascade);
            $mform->setDefault('cascade'.$category->id, $cascade);
            $mform->addElement('checkbox', 'enrolled'.$category->id, $str->enrol);
            $mform->setDefault('enrolled'.$category->id, $enrolled);
            foreach($thisparents as $parentid) {
                $mform->disabledIf('display'.$category->id, 'cascade'.$parentid, 'checked');
                $mform->disabledIf('cascade'.$category->id, 'cascade'.$parentid, 'checked');
                $mform->disabledIf('enrolled'.$category->id, 'cascade'.$parentid, 'checked');
            }
        } else {
            $category->id = '0';
        }

        if ($categories = get_categories($category->id)) {   // Print all the children recursively
            $countcats = count($categories);
            $count = 0;
            $first = true;
            $last = false;
            if (!empty($category->id)) {
                $thisparents[] = $category->id;
            } else {
                $thisparents = array();
            }
            foreach ($categories as $cat) {
                $count++;
                if ($count == $countcats) {
                    $last = true;
                }
                $up = $first ? false : true;
                $down = $last ? false : true;
                $first = false;

                $this->print_category_edit($cat, $displaylist, $parentslist, $depth+1, $up, $down, $thisparents);
            }
        }
    }
}
