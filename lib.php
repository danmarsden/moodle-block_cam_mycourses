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
 * Form for editing HTML block instances.
 *
 * @package   block_cam_mycourses
 * @author    Dan Marsden <dan@danmarsden.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

function display_mycourses() {
    global $USER,$DB;
    static $categories = NULL;
    $return = '';
    //prepare content
    if (is_null($categories)) {
        $categoriescnf = array();
        $config = get_config('block_cam_mycourses');
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
        //get full information on these categories
        $categories = $DB->get_records_select('course_categories', 'id IN('.implode(',', array_keys($categoriescnf)).')', array(),'sortorder');
        foreach ($categories as $cid => $category) {
            $categories[$cid]->config = $categoriescnf[$cid];
        }
    }
    //now display content in block.
    foreach ($categories as $cid => $category) {
        //TODO: neeed to put links on the category name to change which category is displayed.
        $return .= '<div class="mycourse_categories">';
        $return .= '<span class="mycourse_category">'.$category->name.'</span>';
        $return .= "</div>";
    }
    $currentcategory = reset($categories); //DEBUG - this should be obtained from somewhere like Session.
    $return .= '<div class="mycourse_content">';
    $return .= get_mycourse_category_content($currentcategory);
    $return .= '</div>';
    return $return;

}

function get_mycourse_category_content($category) {
    global $USER, $CFG;
    $return = '';
    $cascade = false;
    $courses = array();
    if (!empty($category->config->cascade)) {
        $cascade = true;
    }

    if (!empty($category->config->enroll)) {
        if ($cascade) {
            $categories = get_child_categories($category->id);
            $catid = implode(',', array_keys($categories));
        } else {
            $catid = $category->id;
        }
        $courses = enrol_get_users_courses_by_category($USER->id, $catid);
    } else {
        if ($cascade) {
            //easy - only get a single category of courses.
            $courses = get_courses($category->id);
        } else {
            //get all child categories
            $categories = get_child_categories($category->id);
            $catids = array();
            $catids[] = $category->id;
            foreach ($categories as $c) {
                $catids[] = $c->id;
            }
            //now get courses for all these child categories.
            $courses = get_courses_by_categories(implode(',',$catids));
        }

    }
    if (!empty($courses)) {
        if ($category->config->display == 1) {
            //full listing
            foreach ($courses as $c) { //set last access var
                if (isset($USER->lastcourseaccess[$c->id])) {
                    $courses[$c->id]->lastaccess = $USER->lastcourseaccess[$c->id];
                } else {
                    $courses[$c->id]->lastaccess = 0;
                }
            }
            $return .= mycourses_print_overview($courses);
        } elseif($category->config->display==2) {
            //show list only
            $return .= "<ul>";
            foreach ($courses as $course) {
                $return .= '<li><a href="'.$CFG->wwwroot.'/course/index.php?id='.$course->id.'">'.$course->fullname.'</a></li>';
            }
            $return .="</ul>";
        }
    }

    return $return;
}

/**
 * Returns list of courses user is enrolled into.
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

    //note: we can not use DISTINCT + text fields due to Oracle and MS limitations, that is why we have the subselect there
    $sql = "SELECT $coursefields $ccselect
              FROM {course} c
              JOIN (SELECT DISTINCT e.courseid
                      FROM {enrol} e
                      JOIN {user_enrolments} ue ON (ue.enrolid = e.id AND ue.userid = :userid)
                 $subwhere
                   ) en ON (en.courseid = c.id)
           $ccjoin
             WHERE c.category IN(:catid) AND c.id <> :siteid
          $orderby";
    $params['userid']  = $userid;
    $params['catid'] = $catid;

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
 * Returns list of courses, for whole site, or category
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
 * @param string|int $categoryid Either a category id or 'all' for everything
 * @param string $sort A field and direction to sort by
 * @param string $fields The additional fields to return
 * @return array Array of courses
 */
function get_courses_by_categories($categories, $sort="c.sortorder ASC", $fields="c.*") {

    global $USER, $CFG, $DB;

    $params = array();

    if (!empty($categories)) {
        $categoryselect = "WHERE c.category IN (".$categories.")";
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

function mycourses_print_overview($courses) {
    global $CFG, $USER, $DB, $OUTPUT;
    $return = '';
    $htmlarray = array();
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
        $groups = groups_get_all_groups($course->id, $USER->id);
        //TODO: convert groups fullname to dates from lookup table.
        if (!empty($groups)) {
            $return .= '<span class="mycourse_group">'.format_string(reset($groups)->name).'</span>';
            if (count($groups) > 1) {
                $return .= "<ul>";
                foreach ($groups as $group) {
                    $return .= "<li>".format_string($group->name)."</li>";
                }
                $return .= "</ul>";
            }
        }
        if (array_key_exists($course->id,$htmlarray)) {
            foreach ($htmlarray[$course->id] as $modname => $html) {
                $return .= $html;
            }
        }
        $return .= $OUTPUT->box_end();
    }
    return $return;
}