<?php
/**
 * DokuWiki Plugin authcrowd (Auth Component)
 *
 * @license GPL 3.0 http://www.gnu.org/licenses/gpl-3.0.html
 * @author  Adrian Rumpold <a.rumpold@gmail.com>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

require_once('Services/Atlassian/Crowd.php');

class auth_plugin_authcrowd extends DokuWiki_Auth_Plugin {
    private $user_tokens = array();
    
    /**
     * Constructor.
     */
    public function __construct() {
        parent::__construct(); // for compatibility

        // Fetch and validate configuration parameters        
        $app_name = $this->getConf('app_name');
        $app_pw = $this->getConf('app_password');
        $server_url = $this->getConf('server_url');
        $this->debug = $this->getConf('debug');

        if (empty($app_name) || empty($app_pw) || empty($server_url)) {
            msg($this->getPluginName() . " configuration error -- please supply the 'app_name', 'app_password', and 'server_url' fields", -1, __LINE__, __FILE__);
            $this->success = false;
            return;
        }

        // Attempt to connect to the Crowd server
        try {
            $this->crowd = new Services_Atlassian_Crowd(array(
                'app_name' => $app_name,
                'app_credential' => $app_pw,
                'service_url' => $server_url . "/services/SecurityServer?wsdl"
            ));
            $app_token = $this->crowd->authenticateApplication();
            $this->success = true;
        } catch (Services_Atlassian_Crowd_Exception $e) {
            if ($this->debug) {
                msg("Could not connect to Crowd server: " . $e->getMessage(), -1, __LINE__, __FILE__);
            }    
            $this->success = false;
            return;
        }
    }

    /**
     * Check user name and password against the external directory.
     * 
     * @param string $user the user name
     * @param string $pass the password
     * @return bool
     */
    public function checkPass($user, $pass) {
        if (empty($pass)) {
            return false;
        }
        
        try {
            $token = $this->crowd->authenticatePrincipal($user, $pass, $_SERVER['HTTP_USER_AGENT'], $_SERVER['REMOTE_ADDR']);
            $this->user_tokens[$user] = $token;
            return true;
        } catch (Services_Atlassian_Crowd_Exception $e) {
            if ($this->debug) {
                msg("Could not authenticate user $user: " . $e->getMessage(), -1, __LINE__, __FILE__);
            }
            return false;
        }
    }


    /**
     * Return user info
     *
     * Returns info about the given user needs to contain
     * at least these fields:
     *
     * name string  full name of the user
     * mail string  email addres of the user
     * grps array   list of groups the user is in
     *
     * @param   string $user the user name
     * @return  array containing user data or false
     */
    public function getUserData($user) {
        $token = $this->user_tokens[$user];

        if (empty($token) || !$this->validateToken($token)) {
            return false;
        }
        
        try {
            $principal = $this->crowd->findPrincipalByToken($token);

            // Extract user attributes
            $info = array();
            foreach ($principal->attributes->SOAPAttribute as $attr) {
                dbglog($attr->name . ": " . $attr->values->string);
                $key = $attr->name;
                if ($attribute->name === 'displayName') {
                    $key = 'name';
                }

                $info[$key] = $attr->values->string;
            }

            // Fetch group memberships
            $groups = reset($this->crowd->findGroupMemberships($user));
            dbglog("Groups for $user: " . join(', ', $groups));
            $info['grps'] = $groups;

            return $info;
        } catch (Services_Atlassian_Crowd_Exception $e) {
            msg("Could not fetch user data for $user: " . $e->getMessage(), -1, __LINE__, __FILE__);
            return false;
        }
    }

    private function validateToken($token) {
        try {
            return $this->crowd->isValidPrincipalToken($token, $_SERVER['HTTP_USER_AGENT'], $_SERVER['REMOTE_ADDR']);
        } catch (Services_Atlassian_Crowd_Exception $e) {
            msg("Failed to validate user token: " . $e->getMessage(), -1, __LINE__, __FILE__);
            return false;
        }
    }

    /**
     * Return case sensitivity of the backend
     *
     * When your backend is caseinsensitive (eg. you can login with USER and
     * user) then you need to overwrite this method and return false
     *
     * @return bool
     */
    public function isCaseSensitive() {
        return true;
    }

    /**
     * Sanitize a given username
     *
     * This function is applied to any user name that is given to
     * the backend and should also be applied to any user name within
     * the backend before returning it somewhere.
     *
     * This should be used to enforce username restrictions.
     *
     * @param string $user username
     * @return string the cleaned username
     */
    public function cleanUser($user) {
        return $user;
    }

    /**
     * Sanitize a given groupname
     *
     * This function is applied to any groupname that is given to
     * the backend and should also be applied to any groupname within
     * the backend before returning it somewhere.
     *
     * This should be used to enforce groupname restrictions.
     *
     * Groupnames are to be passed without a leading '@' here.
     *
     * @param  string $group groupname
     * @return string the cleaned groupname
     */
    public function cleanGroup($group) {
        return $group;
    }

    /**
     * Check Session Cache validity [implement only where required/possible]
     *
     * DokuWiki caches user info in the user's session for the timespan defined
     * in $conf['auth_security_timeout'].
     *
     * This makes sure slow authentication backends do not slow down DokuWiki.
     * This also means that changes to the user database will not be reflected
     * on currently logged in users.
     *
     * To accommodate for this, the user manager plugin will touch a reference
     * file whenever a change is submitted. This function compares the filetime
     * of this reference file with the time stored in the session.
     *
     * This reference file mechanism does not reflect changes done directly in
     * the backend's database through other means than the user manager plugin.
     *
     * Fast backends might want to return always false, to force rechecks on
     * each page load. Others might want to use their own checking here. If
     * unsure, do not override.
     *
     * @param  string $user - The username
     * @return bool
     */
    //public function useSessionCache($user) {
      // FIXME implement
    //}
}

// vim:ts=4:sw=4:et: