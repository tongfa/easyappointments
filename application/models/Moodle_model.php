<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Open Source Web Scheduler
 *
 * @package     tutti
 * @author      Chris David
 * @copyright   Copyright (c) 2024, Chris David
 * @license     http://opensource.org/licenses/GPL-3.0 - GPLv3
 * ---------------------------------------------------------------------------- */

/**
 * Moodle Model
 *
 * Contains methods to interact with Moodle.
 *
 * @package Models
 */
class Moodle_model extends EA_Model {
    /**
     * User_Model constructor.
     */
    private $moodledb;

    public function __construct()
    {
        parent::__construct();
        $this->load->library('timezones');
        $this->moodledb = $moodledb = $this->load->database('moodle', TRUE);
        // $this->load->helper('general');
        // $this->load->helper('string');
    }

    /**
     * Returns the moodle user_id given a moodle session.
     *
     * @return array Returns user_id
     */
    public function check_session()
    {
        $session_id = $_COOKIE['MoodleSession'];

        $session = $this->moodledb->get_where('mdl_sessions', ['sid' => $session_id, 'state' => 0])->row_array();

        if (empty($session))
        {
            return NULL;
        }

        return $session['userid'];
    }

    public function new_providers() {
        $new_providers = $this->moodledb
        ->select('mdl_user.*')
        ->from('mdl_user')
        ->join('mdl_role_assignments', 'mdl_role_assignments.userid = mdl_user.id')
        ->join('mdl_role', 'mdl_role_assignments.roleid = mdl_role.id')
        ->where_in('mdl_role.shortname', ['editing_teacher', 'teacher'])
        ->where('mdl_user.id not in (select id from ea_users)')
        ->get();
        return $new_providers->result();
    }

    public function get_user($user_id) {
        $user = $this->moodledb->get_where('mdl_user', ['id' => $user_id])->row_array();

        if (empty($user))
        {
            return NULL;
        }

        $SERVER_DEFAULT = 99; // Moodle default
        $use_moodle_timezone = isset($user['timezone']) && $user['timezone'] != $SERVER_DEFAULT;

        $role_slug = 'customer'; // fallback if nothing else sets role_slug.

        // are they an admin?
        $admin_list_text = $this->moodledb->get_where('mdl_config', ['name' => 'siteadmins'])->row_array();
        $siteadmins = explode(',', $admin_list_text['value']);
        if (in_array($user_id, $siteadmins)) {
            $role_slug = 'admin';
        } else {
            // not an admin, could they be a teacher?
            // check if this person is listed as a teacher on any course. If so, then they are a provider.
            $moodle_roles = $this->moodledb
                ->select('mdl_role.shortname')
                ->from('mdl_role')
                ->join('mdl_role_assignments', 'mdl_role_assignments.roleid = mdl_role.id')
                ->where('mdl_role_assignments.userid = '. $user_id)
                ->get();
            foreach ($moodle_roles->result() as $row) {
                if ($row->shortname == 'teacher' || $row->shortname == 'editingteacher') {
                    $role_slug = 'provider';
                    break;
                }
            }
        }

        $ea_role = $this->db->get_where('roles', ['slug' => $role_slug])->row_array();

        $default_timezone = $this->timezones->get_default_timezone();

        return [
            'id' => $user['id'],
            'first_name' => $user['firstname'],
            'last_name' => $user['lastname'],
            'email' => $user['email'],
            'username' => $user['username'],
            'mobile_number' => $user['phone1'],
            'phone_number' => $user['phone2'],
            'address' => 'NOT AVAILABLE',
            'city' => 'NOT AVAILABLE',
            'state' => 'NOT AVAILABLE',
            'zip_code' => 'NOT AVAILABLE',
            'timezone' => $use_moodle_timezone ? $user['timezone'] : $default_timezone,
            'language' => 'english', // TODO should this be language of moodle?
            'id_roles' => $ea_role['id'],
            'role_slug' => $role_slug,
        ];

    }

}