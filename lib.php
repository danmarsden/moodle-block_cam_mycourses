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
 * Block for displaying courses.
 *
 * @package   block_cam_mycourses
 * @author    Dan Marsden <dan@danmarsden.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

//main function to get the content of the block.
function display_mycourses() {
    global $USER,$DB,$PAGE,$CFG;
    static $categories = NULL;
    static $categoriescnf = NULL;
    $return = '';
    //prepare content
    if (is_null($categories)) {
        $categoriescnf = array();
        $config = get_config('block_cam_mycourses');
        if (empty($config)) {
            return '';
        }
        foreach ($config as $name => $value) {
            $cid = (int)substr($name, strpos($name, '_')+1);
            $var = str_replace('_'.$cid, '', $name);
            $categoriescnf[$cid]->$var = $value;
        }
        //now clear out categories in the array we don't need
        foreach ($categoriescnf as $cid => $category) {
            if (empty($category->display)) {
                unset($categoriescnf[$cid]);
            }
        }
        if (empty($categoriescnf)) {
            return '';
        }
        //get full information on these categories
        $categories = $DB->get_records_select('course_categories', 'id IN('.implode(',', array_keys($categoriescnf)).')', array(),'sortorder');
    }

    $currentcategory = optional_param('mycoursecat', '', PARAM_INT);
    if (empty($currentcategory) || !isset($categories[$currentcategory])) {
        $currentcategory = reset($categories)->id; //get content from top category.
    } else {
        $currentcategory = $categories[$currentcategory]->id;
    }

    //now display content in block.
    $return .= '<div class="mycourse_categories">';
    foreach ($categories as $cid => $category) {
        $url = new moodle_url($PAGE->url, array('mycoursecat'=>$cid));
        $return .= '<span class="mycourse_category';
        if ($currentcategory == $cid) {
            $return .= ' selected';
        }
        $return .= '"><a href="'.$url.'" id="category'.$category->id.'">'.$category->name.'</a></span>';
    }
    $return .= "</div>";

    $return .= '<div id="mycourseframe'.$currentcategory.'" class="mycourse_content">';
    if (mycourses_use_js_view()) {
        $jsmodule = array(
            'name'     => 'blocks_cam_mycourses',
            'fullpath' => '/blocks/cam_mycourses/module.js',
            'requires' => array('node-base','io-base'),
            'strings' => array());
        $PAGE->requires->js_init_call('M.blocks_cam_mycourses.init', array($CFG->wwwroot.'/blocks/cam_mycourses/loaddisplay.php?id='), false, $jsmodule);
    }
    $return .= get_mycourse_category_content($currentcategory, $categoriescnf[$currentcategory]->cascade,
                                             $categoriescnf[$currentcategory]->enrol, $categoriescnf[$currentcategory]->display);
    $return .= '</div>';
    return $return;

}
//displays the content for a given category.
function get_mycourse_category_content($categoryid, $cascade, $enroll, $display) {
    global $USER, $CFG;
    require_once($CFG->dirroot.'/course/lib.php');
    $return = '';
    $courses = array();
    if (!empty($cascade)) {
        $cascade = true;
    } else {
        $cascade = false;
    }

    $catids = array();
    if ($cascade) {
        $catids = recursive_get_child_categories($categoryid);
    }
    $catids[] = $categoryid;

    if (!empty($enroll)) {
        $courses = enrol_get_users_courses_by_category($USER->id, $catids, false, 'modinfo');
    } else {
        $courses = get_courses_by_categories($catids);
    }
    if (!empty($courses)) {
        if ($display == 1) {
            //full listing
            foreach ($courses as $c) { //set last access var
                if (isset($USER->lastcourseaccess[$c->id])) {
                    $courses[$c->id]->lastaccess = $USER->lastcourseaccess[$c->id];
                } else {
                    $courses[$c->id]->lastaccess = 0;
                }
            }
            $return .= mycourses_print_overview($courses, $enroll);
        } elseif($display==2) {
            //show list only
            $return .= "<ul>";
            foreach ($courses as $course) {
                $return .= '<li><a href="'.$CFG->wwwroot.'/course/view.php?id='.$course->id.'">'.$course->fullname.'</a></li>';
            }
            $return .="</ul>";
        }
    }

    return $return;
}

/**
 * Returns list of courses user is enrolled into. - modified core function to allow search by categories.
 *
 * - $fields is an array of fieldnames to ADD
 *   so name the fields you really need, which will
 *   be added and uniq'd
 *
 * @param int $userid
 * @param bool $onlyactive return only active enrolments in courses user may see
 * @param string|array $fields
 * @param string $sort
 * @return array
 */
function enrol_get_users_courses_by_category($userid, $catid, $onlyactive = false, $fields = NULL, $sort = 'visible DESC,sortorder ASC') {
    global $DB;

    // Guest account does not have any courses
    if (isguestuser($userid) or empty($userid)) {
        return(array());
    }

    $basefields = array('id', 'category', 'sortorder',
                        'shortname', 'fullname', 'idnumber',
                        'startdate', 'visible',
                        'groupmode', 'groupmodeforce');

    if (empty($fields)) {
        $fields = $basefields;
    } else if (is_string($fields)) {
        // turn the fields from a string to an array
        $fields = explode(',', $fields);
        $fields = array_map('trim', $fields);
        $fields = array_unique(array_merge($basefields, $fields));
    } else if (is_array($fields)) {
        $fields = array_unique(array_merge($basefields, $fields));
    } else {
        throw new coding_exception('Invalid $fileds parameter in enrol_get_my_courses()');
    }
    if (in_array('*', $fields)) {
        $fields = array('*');
    }

    $orderby = "";
    $sort    = trim($sort);
    if (!empty($sort)) {
        $rawsorts = explode(',', $sort);
        $sorts = array();
        foreach ($rawsorts as $rawsort) {
            $rawsort = trim($rawsort);
            if (strpos($rawsort, 'c.') === 0) {
                $rawsort = substr($rawsort, 2);
            }
            $sorts[] = trim($rawsort);
        }
        $sort = 'c.'.implode(',c.', $sorts);
        $orderby = "ORDER BY $sort";
    }

    $params = array('siteid'=>SITEID);

    if ($onlyactive) {
        $subwhere = "WHERE ue.status = :active AND e.status = :enabled AND ue.timestart < :now1 AND (ue.timeend = 0 OR ue.timeend > :now2)";
        $params['now1']    = round(time(), -2); // improves db caching
        $params['now2']    = $params['now1'];
        $params['active']  = ENROL_USER_ACTIVE;
        $params['enabled'] = ENROL_INSTANCE_ENABLED;
    } else {
        $subwhere = "";
    }

    $coursefields = 'c.' .join(',c.', $fields);
    list($ccselect, $ccjoin) = context_instance_preload_sql('c.id', CONTEXT_COURSE, 'ctx');

    list($in_sql, $in_params) = $DB->get_in_or_equal($catid, SQL_PARAMS_NAMED);
    //note: we can not use DISTINCT + text fields due to Oracle and MS limitations, that is why we have the subselect there
    $sql = "SELECT $coursefields $ccselect
              FROM {course} c
              JOIN (SELECT DISTINCT e.courseid
                      FROM {enrol} e
                      JOIN {user_enrolments} ue ON (ue.enrolid = e.id AND ue.userid = :userid)
                 $subwhere
                   ) en ON (en.courseid = c.id)
           $ccjoin
             WHERE c.category ".$in_sql." AND c.id <> :siteid
          $orderby";
    $params['userid']  = $userid;
    $params = array_merge($in_params, $params);

    $courses = $DB->get_records_sql($sql, $params);

    // preload contexts and check visibility
    foreach ($courses as $id=>$course) {
        context_instance_preload($course);
        if ($onlyactive) {
            if (!$course->visible) {
                if (!$context = get_context_instance(CONTEXT_COURSE, $id)) {
                    unset($courses[$id]);
                    continue;
                }
                if (!has_capability('moodle/course:viewhiddencourses', $context, $userid)) {
                    unset($courses[$id]);
                    continue;
                }
            }
        }
        $courses[$id] = $course;
    }

    //wow! Is that really all? :-D

    return $courses;

}

/**
 * Returns list of courses given a list of category ids
 *
 * Returns list of courses, for whole site, or category
 * Important: Using c.* for fields is extremely expensive because
 *            we are using distinct. You almost _NEVER_ need all the fields
 *            in such a large SELECT
 *
 * @global object
 * @global object
 * @global object
 * @uses CONTEXT_COURSE
 * @param string $categories 
 * @param string $sort A field and direction to sort by
 * @param string $fields The additional fields to return
 * @return array Array of courses
 */
function get_courses_by_categories($categories, $sort="c.sortorder ASC", $fields="c.*") {

    global $USER, $CFG, $DB;

    $params = array();

    if (!empty($categories)) {
        list($in_sql, $params) = $DB->get_in_or_equal($categories);
        $categoryselect = "WHERE c.category ".$in_sql;
    } else {
        $categoryselect = "";
    }

    if (empty($sort)) {
        $sortstatement = "";
    } else {
        $sortstatement = "ORDER BY $sort";
    }

    $visiblecourses = array();

    list($ccselect, $ccjoin) = context_instance_preload_sql('c.id', CONTEXT_COURSE, 'ctx');

    $sql = "SELECT $fields $ccselect
              FROM {course} c
           $ccjoin
              $categoryselect
              $sortstatement";

    // pull out all course matching the cat
    if ($courses = $DB->get_records_sql($sql, $params)) {
        // loop throught them
        foreach ($courses as $course) {
            context_instance_preload($course);
            if (isset($course->visible) && $course->visible <= 0) {
                // for hidden courses, require visibility check
                if (has_capability('moodle/course:viewhiddencourses', get_context_instance(CONTEXT_COURSE, $course->id))) {
                    $visiblecourses [$course->id] = $course;
                }
            } else {
                $visiblecourses [$course->id] = $course;
            }
        }
    }
    return $visiblecourses;
}
//Modified print_overview function to add display of group information.
function mycourses_print_overview($courses, $enroll=false) {
    global $CFG, $USER, $DB, $OUTPUT;
    $return = '';
    $htmlarray = array();
    //array of weekdays to replace
    $weekdaysearch = array();
    $weekdayreplace = array();
    $weekdaysearch[] = get_string('monday', 'calendar').', ';
    $weekdayreplace[] = '';
    $weekdaysearch[] = get_string('tuesday', 'calendar').', ';
    $weekdayreplace[] = '';
    $weekdaysearch[] = get_string('wednesday', 'calendar').', ';
    $weekdayreplace[] = '';
    $weekdaysearch[] = get_string('thursday', 'calendar').', ';
    $weekdayreplace[] = '';
    $weekdaysearch[] = get_string('friday', 'calendar').', ';
    $weekdayreplace[] = '';
    $weekdaysearch[] = get_string('saturday', 'calendar').', ';
    $weekdayreplace[] = '';
    $weekdaysearch[] = get_string('sunday', 'calendar').', ';
    $weekdayreplace[] = '';

    if (strtoupper(substr(PHP_OS, 0, 3)) == 'WIN') {
        $dateformat = "%#d/%m/%Y";
    } else {
        $dateformat = "%e/%m/%Y";
    }
    if ($modules = $DB->get_records('modules')) {
        foreach ($modules as $mod) {
            if (file_exists(dirname(dirname(dirname(__FILE__))).'/mod/'.$mod->name.'/lib.php')) {
                include_once(dirname(dirname(dirname(__FILE__))).'/mod/'.$mod->name.'/lib.php');
                $fname = $mod->name.'_print_overview';
                if (function_exists($fname)) {
                    $fname($courses,$htmlarray);
                }
            }
        }
    }
    foreach ($courses as $course) {
        $return .= $OUTPUT->box_start('coursebox');
        $attributes = array('title' => s($course->fullname));
        if (empty($course->visible)) {
            $attributes['class'] = 'dimmed';
        }
        $return .= $OUTPUT->heading(html_writer::link(
            new moodle_url('/course/view.php', array('id' => $course->id)), format_string($course->fullname), $attributes), 3);
        $groupurl = new moodle_url('/user/index.php', array('id'=>$course->id));
        if (!empty($course->groupmode)) { //only show groups if groupmode is set.
            if (mycourses_custom_group_table()) {
                $groups = cam_groups_get_all_groups($course->id, $USER->id);
                if (!empty($groups)) {
                    //find current group
                    $currentgroup = null;
                    foreach ($groups as $group) {
                        if ($group->startdate > time() && empty($currentgroup)) {
                            $currentgroup = $group;
                        }
                    }
                    if (empty($currentgroup)) {
                        $currentgroup = end($groups);
                        reset($groups);
                    }
                    $gname = userdate($currentgroup->startdate,$dateformat). " - " . userdate($currentgroup->enddate,$dateformat);
                }
            } else {
               $groups = groups_get_all_groups($course->id, $USER->id);
               if (!empty($groups)) {
                  $gname = reset($groups)->name;
               }
            }

            if (!empty($groups)) {
                $groupurl = new moodle_url($groupurl, array('group'=>reset($groups)->id));
                if (count($groups) > 1) {
                    $return .= '<span class="mycourse_group_list"><a href="'.$groupurl.'">'.format_string($gname).'</a>';
                    $return .= '<ul class="mycourse_grouplist">';
                    foreach ($groups as $group) {
                        $groupurl = new moodle_url($groupurl, array('group'=>$group->id));
                        if (mycourses_custom_group_table()) {
                            $gname = userdate($group->startdate,$dateformat). " - " . userdate($group->enddate,$dateformat);
                        } else {
                            $gname = $group->name;
                        }

                        $return .= '<li><a href="'.$groupurl.'">'.format_string($gname)."</a></li>";
                    }
                    $return .= "</ul></span>";
                } else {
                    $return .= '<span class="mycourse_group"><a href="'.$groupurl.'">'.format_string($gname).'</a></span>';
                }
            } elseif ($enroll) {
                //user is enrolled in this course, so show a link to view participants.
                $return .= '<span class="mycourse_group"><a href="'.$groupurl.'">'.get_string('participantslist').'</a></span>';
            }
        } elseif ($enroll) {
            //user is enrolled in this course, so show a link to view participants.
            $return .= '<span class="mycourse_group"><a href="'.$groupurl.'">'.get_string('participantslist').'</a></span>';
        }
        if (array_key_exists($course->id,$htmlarray)) {
            foreach ($htmlarray[$course->id] as $modname => $html) {
                $html = str_replace('<br />', ', ', $html); //strip out carriage returns
                //strip out weekday names from "info"
                $html = str_ireplace($weekdaysearch,$weekdayreplace, $html);
                $return .= '<span class="mycourse_moduleoverview">'.$html."</span>";
            }
        }
        $return .= $OUTPUT->box_end();
    }
    return $return;
}

function mycourses_use_js_view() {
    if (strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE 6')) {
        return false;
    }
    return true;
}

function mycourses_custom_group_table() {
    static $hascustomtable = NULL;
    if (is_null($hascustomtable)) {
        global $DB;
        $dbman = $DB->get_manager();
        $hascustomtable = $dbman->table_exists('block_mycourses_group_detail');
    }
    return $hascustomtable;
}

/**
 * modified groups_get_all_groups function to return custom cambridge data.
 * Gets array of all groups in a specified course.
 *
 * @param int $courseid The id of the course.
 * @param mixed $userid optional user id or array of ids, returns only groups of the user.
 * @param int $groupingid optional returns only groups in the specified grouping.
 * @param string $fields
 * @return array|bool Returns an array of the group objects or false if no records
 * or an error occurred. (userid field returned if array in $userid)
 */
function cam_groups_get_all_groups($courseid, $userid=0, $groupingid=0, $fields='g.*, gd.startdate, gd.enddate') {
    global $CFG, $DB;

    if (empty($userid)) {
        $userfrom  = "";
        $userwhere = "";
        $params = array();

    } else {
        list($usql, $params) = $DB->get_in_or_equal($userid);
        $userfrom  = ", {groups_members} gm";
        $userwhere = "AND g.id = gm.groupid AND gm.userid $usql";
    }

    if (!empty($groupingid)) {
        $groupingfrom  = ", {groupings_groups} gg";
        $groupingwhere = "AND g.id = gg.groupid AND gg.groupingid = ?";
        $params[] = $groupingid;
    } else {
        $groupingfrom  = "";
        $groupingwhere = "";
    }

    array_unshift($params, $courseid);

    return $DB->get_records_sql("SELECT $fields
                                   FROM {groups} g, {block_mycourses_group_detail} gd $userfrom $groupingfrom
                                  WHERE g.courseid = ? AND gd.groupid = g.id
                                  $userwhere $groupingwhere
                               ORDER BY  gd.startdate ASC, gd.enddate ASC", $params);
}

function recursive_get_child_categories($categoryid) {
    $catids = array();
    $categories = get_child_categories($categoryid);
    foreach ($categories as $c) {
        $catids[] = $c->id;
        $childcats = recursive_get_child_categories($c->id);
        $catids = array_merge($catids, $childcats);
    }
    return $catids;
}
