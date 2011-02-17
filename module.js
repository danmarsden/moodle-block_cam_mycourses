/**
 * Javascript helper function for cam_mycourses block
 *
 * @package    blocks
 * @subpackage cam_mycourses
 * @author     Dan Marsden  {@link http://danmarsden.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

M.blocks_cam_mycourses = M.blocks_cam_mycourses || {};

M.blocks_cam_mycourses.init = function(Y, url) {
    var handleClick = function(e) {
        // pass the event facade to the logger or console for inspection:
       //now change the location of the doc object to the new href
       var frame = Y.one('#mycourseframe');

       frame.setAttribute('data', url+this.getAttribute('id').replace("category",""));
       var selectedcat = Y.one('.mycourse_categories .selected');
       selectedcat.removeClass('selected');
       this.get('parentNode').addClass('selected');

       e.halt();
    };

    //elements can be targeted using selector syntax:
    Y.on("click", handleClick, ".mycourse_category a");

}