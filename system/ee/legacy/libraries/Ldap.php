<?php
/**
 * This source file is part of the open source project
 * ExpressionEngine (https://expressionengine.com)
 *
 * @link      https://expressionengine.com/
 * @copyright Copyright (c) 2003-2022, Packet Tide, LLC (https://www.packettide.com)
 * @license   https://expressionengine.com/license Licensed under Apache License, Version 2.0
 */

/**
 * Core LDAP. Dust. Unused 12 year old experiment.
 */
class EE_LDAP
{
    public $conn = false;
    public $info = false;
    public $eedata = array('screen_name',
        'username',
        'email',
        'unique_id',
        'group_id',
        'url',
        'location',
        'language',
        'timezone',
        'time_format',
        'bday_y',
        'bday_m',
        'bday_d',
        'occupation',
        'interests',
        'aol_im',
        'icq',
        'yahoo_im',
        'msn_im',
        'bio');

    /** -------------------------------------
    /**  Constructor
    /** -------------------------------------*/
    public function __construct()
    {
        if (ee()->config->item('ldap_enabled') === false or ee()->config->item('ldap_enabled') != 'y') {
            return false;
        }

        if (! function_exists('ldap_connect')) {
            $this->output_error('LDAP Support is Unavailable');

            return false;
        }

        if (ee()->config->item('ldap_server') === false or ee()->config->item('ldap_server') == '') {
            $this->output_error('Missing LDAP Settings');

            return false;
        }
    }

    /** -------------------------------------
    /**  LDAP Register User
    /** -------------------------------------*/
    public function register($data)
    {
        /** -------------------------------------
        /**  Make a LDAP (Love Da Paul) Connection
        /** -------------------------------------*/
        if (! $this->create_connection()) {
            return false;
        }

        /** -------------------------------------
        /**  Set Main Attributes
        /** -------------------------------------*/
        $insert['cn'] = $data['screen_name'];
        $insert['uid'] = $data['username'];
        $insert['mail'] = $data['email'];

        $insert['inetOrgPerson']['0'] = 'inetOrgPerson';

        /** -------------------------------------
        /**  Set EE Attributes
        /** -------------------------------------*/
        foreach ($this->eedata as $value) {
            if (isset($data[$value])) {
                $insert['exp_' . $value] = $data[$value];
            }
        }

        // -------------------------------------------
        // 'ldap_register_attributes' hook.
        //  - Allows adding of attributes
        //  - Added 1.4.2
        //
        if (ee()->extensions->active_hook('ldap_register_attributes') === true) {
            $insert = ee()->extensions->call('ldap_register_attributes', $insert, $data);
            if (ee()->extensions->end_script === true) {
                return;
            }
        }
        //
        // -------------------------------------------

        /** -------------------------------------
        /**  Add Data
        /** -------------------------------------*/
        $dn = (ee()->config->item('ldap_base_dn') == '') ? 'cn=' . $data['screen_name'] : 'cn=' . $data['screen_name'] . ', ' . ee()->config->item('ldap_base_dn');

        if (! $result = @ldap_add($this->conn, $dn, $insert)) {
            $this->output_error();

            return false;
        }

        /** -------------------------------------
        /**  Close Connection and Return
        /** -------------------------------------*/
        @ldap_close($this->conn);

        return true;
    }

    /** -------------------------------------
    /**  LDAP Modify User Data
    /** -------------------------------------*/
    public function modify($data)
    {
        /** -------------------------------------
        /**  Make a LDAP (Love Da Paul) Connection
        /** -------------------------------------*/
        if (! $this->create_connection()) {
            return false;
        }

        /** -------------------------------------
        /**  Check for Person
        /** -------------------------------------*/
        if (! $this->member_search($data['username'])) {
            return false;
        }

        /** -------------------------------------
        /**  Set Attributes
        /** -------------------------------------*/
        $insert['cn'] = $data['screen_name'];
        $insert['uid'] = $data['username'];
        $insert['mail'] = $data['email'];

        foreach ($this->eedata as $value) {
            if (isset($data[$value])) {
                $insert['exp_' . $value] = $data[$value];
            }
        }

        // -------------------------------------------
        // 'ldap_modify_attributes' hook.
        //  - Allows adding of additional attributes
        //  - Added 1.4.2
        //
        if (ee()->extensions->active_hook('ldap_register_attributes') === true) {
            $insert = ee()->extensions->call('ldap_register_attributes', $insert, $data);
            if (ee()->extensions->end_script === true) {
                return;
            }
        }
        //
        // -------------------------------------------

        /** -------------------------------------
        /**  Modify Data
        /** -------------------------------------*/
        $dn = (ee()->config->item('ldap_base_dn') == '') ? 'cn=' . $data['screen_name'] : 'cn=' . $data['screen_name'] . ', ' . ee()->config->item('ldap_base_dn');

        if (! $result = @ldap_modify($this->conn, $dn, $insert)) {
            $this->output_error();

            return false;
        }

        /** -------------------------------------
        /**  Close Connection and Return
        /** -------------------------------------*/
        @ldap_close($this->conn);

        return true;
    }

    /** -------------------------------------
    /**  Perform Search for User
    /** -------------------------------------*/
    public function member_search($username)
    {
        $this->create_connection();

        /** -------------------------------------
        /**  Perform Search for User
        /** -------------------------------------*/
        $search = "(&(uid=$username))";
        $attributes = array('cn', 'sn');

        foreach ($this->eedata as $value) {
            $attributes[] = 'exp_' . $value;
        }

        if (! $result = @ldap_search($this->conn, ee()->config->item('ldap_base_dn'), $search, $attributes)) {
            $this->output_error();

            return false;
        }

        if (! $this->info = @ldap_get_entries($this->conn, $result)) {
            $this->output_error();

            return false;
        }

        /** -------------------------------------
        /**  Check
        /** -------------------------------------*/
        if (! isset($this->info['0']) or $this->info['0']['dn'] == '') {
            $this->output_error('User Not Found');

            return false;
        }
    }

    /** -------------------------------------
    /**  Begin LDAP Member Lookup
    /** -------------------------------------*/
    public function authenticate($username, $password)
    {
        $username = ee('Security/XSS')->clean($username);
        $password = ee('Security/XSS')->clean($password);

        /** -------------------------------------
        /**  Make a LDAP (Love Da Paul) Connection
        /** -------------------------------------*/
        if (! $this->create_connection()) {
            return false;
        }

        /** -------------------------------------
        /**  Find Member
        /** -------------------------------------*/
        if (! $this->member_search($username)) {
            return false;
        }

        /** -------------------------------------
        /**  Bind as User with Password - Authentication
        /** -------------------------------------*/
        if (! @ldap_bind($this->conn, $this->info['0']['dn'], $password)) {
            $this->output_error();

            return false;
        }

        /** -------------------------------------
        /**  Prepare Data
        /** -------------------------------------*/
        $data = $this->info['0'];

        foreach ($this->eedata as $value) {
            if (isset($this->info['0']['exp_' . $value])) {
                if (is_array($this->info['0']['exp_' . $value])) {
                    $data[$value] = array_shift($this->info['0']['exp_' . $value]);
                } else {
                    $data[$value] = 'exp_' . $value;
                }
            }
        }

        if (ee()->config->item('ldap_debugging') == 'y') {
            print_r($data);
        }

        /** -------------------------------------
        /**  Close Connection
        /** -------------------------------------*/
        @ldap_close($this->conn);

        /** -------------------------------------
        /**  Return Data
        /** -------------------------------------*/

        return $data;
    }

    /** -------------------------------------
    /**  Create Connection to LDAP
    /** -------------------------------------*/
    public function create_connection()
    {
        /** -------------------------------------
        /**  Connect to Server
        /** -------------------------------------*/
        if ($this->conn !== false) {
            return false;
        }

        $port = (ee()->config->item('ldap_port') === false or ee()->config->item('ldap_port') == '') ? 389 : ee()->config->item('ldap_port');

        if (! $this->conn = @ldap_connect(ee()->config->item('ldap_server'), $port)) {
            $this->output_error();

            return false;
        }

        ldap_set_option($this->conn, LDAP_OPT_PROTOCOL_VERSION, 3);

        /** -------------------------------------
        /**  Enable DLS
        /** -------------------------------------*/
        if (ee()->config->item('ldap_enable_dls') == 'y') {
            if (! @ldap_start_tls($this->conn)) {
                $this->output_error();

                return false;
            }
        }

        /** -------------------------------------
        /**  Bind!
        /** -------------------------------------*/
        if (! ee()->config->item('ldap_manager_dn') && ! ee()->config->item('ldap_manager_pass')) {
            if (! @ldap_bind($this->conn)) {
                $this->output_error();

                return false;
            }
        } else {
            if (! preg_match('/^(\w+=\w+,)*\w+=\w+$/', ee()->config->item('ldap_manager_dn'))) {
                $this->output_error('Manager DN is invalidly formed');

                return false;
            }

            if (! @ldap_bind($this->conn, ee()->config->item('ldap_manager_dn'), ee()->config->item('ldap_manager_pass'))) {
                $this->output_error();

                return false;
            }
        }
    }

    /** -------------------------------------
    /**  Output an LDAP Error
    /** -------------------------------------*/
    public function output_error($error = '')
    {
        if (ee()->config->item('ldap_debugging') == 'y') {
            $error_no = '';

            if ($error == '') {
                $error_no = @ldap_errno($this->conn);
                $error = @ldap_err2str($error_no);
            }

            @trigger_error(printf("<b>LDAP Error</b>: " . $error_no . ": %s<br />\n\n", $error), E_USER_WARNING);
        }

        if ($this->conn !== false) {
            @ldap_close($this->conn);
        }

        exit;
    }
}

// EOF
