<?php
/**
 * Zikula Application Framework
 *
 * @copyright (c), Zikula Development Team
 * @link http://www.zikula.org
 * @version $Id: pnuser.php 118 2010-03-12 10:40:23Z yokav $
 * @license GNU/GPL - http://www.gnu.org/copyleft/gpl.html
 * @package Zikula_System_Modules
 * @subpackage Profile
 */

class Profile_Controller_User extends Zikula_AbstractController
{
    /**
     * the main user function
     *
     * @author Mark West
     * @return string HTML string
     */
    public function main()
    {
        // showing account links is not longer part of the Profile module, redirect to Users:
        return System::redirect(ModUtil::url('Users', 'user', 'main'));
    }

    /**
     * display item
     *
     * @author Mark West
     * @return string HTML string
     */
    public function view($args)
    {
        // Security check
        if (!SecurityUtil::checkPermission('Profile::', '::', ACCESS_READ) || !SecurityUtil::checkPermission('Profile:view:', '::', ACCESS_READ)) {
            return LogUtil::registerPermissionError();
        }

        // Get parameters from whatever input we need.
        $uid   = (int)FormUtil::getPassedValue('uid', isset($args['uid']) ? $args['uid'] : null, 'GET');
        $uname = FormUtil::getPassedValue('uname', isset($args['uname']) ? $args['uname'] : null, 'GET');
        $page  = FormUtil::getPassedValue('page', isset($args['page']) ? $args['page'] : null, 'GET');

        // Getting uid by uname
        if (!empty($uname)) {
            $uid = UserUtil::getIdFromName($uname);
        } elseif (empty($uid)) {
            $uid = UserUtil::getVar('uid');
        }

        // Check for an invalid uid (uid = 1 is the anonymous user)
        if ($uid < 2) {
            return LogUtil::registerError($this->__('Error! Could not find this user.'), 404);
        }

        // Get all the user data
        $userinfo = UserUtil::getVars($uid);

        if (!$userinfo) {
            return LogUtil::registerError($this->__('Error! Could not find this user.'), 404);
        }

        // Check if the user is watching its own profile or if he is admin
        // TODO maybe remove the four lines below
        $currentuser = UserUtil::getVar('uid');
        $ismember    = ($currentuser >= 2);
        $isowner     = ($currentuser == $uid);
        $isadmin     = SecurityUtil::checkPermission('Profile::', '::', ACCESS_ADMIN);

        // Get all active profile fields
        $activeduds = ModUtil::apiFunc('Profile', 'user', 'getallactive',
                array('get' => 'viewable',
                'uid' => $uid));

        // Fill the DUD values array
        $dudarray = array();
        foreach (array_keys($activeduds) as $dudattr) {
            $dudarray[$dudattr] = isset($userinfo['__ATTRIBUTES__'][$dudattr]) ? $userinfo['__ATTRIBUTES__'][$dudattr] : '';
        }

        // Create output object
        $this->view->setCaching(false)->add_core_data();

        $this->view->assign('dudarray', $dudarray)
            ->assign('fields',   $activeduds)
            ->assign('uid',      $userinfo['uid'])
            ->assign('uname',    $userinfo['uname'])
            ->assign('userinfo', $userinfo)
            ->assign('ismember', $ismember)
            ->assign('isadmin',  $isadmin)
            ->assign('sameuser', $isowner);

        // Return the output that has been generated by this function
        if (!empty($page)) {
            if ($this->view->template_exists("profile_user_view_{$page}.tpl")) {
                return $this->view->fetch("profile_user_view_{$page}.tpl", $uid);
            } else {
                return LogUtil::registerError($this->__f('Error! Could not find profile page [%s].', DataUtil::formatForDisplay($page)), 404);
            }
        }

        return $this->view->fetch('profile_user_view.tpl', $uid);
    }

    /**
     * modify a users profile information
     *
     * @author Franky Chestnut
     */
    public function modify($args)
    {
        // Security check
        if (!UserUtil::isLoggedIn() || !SecurityUtil::checkPermission('Profile::', '::', ACCESS_READ)) {
            return LogUtil::registerPermissionError();
        }

        // The API function is called.
        $items = ModUtil::apiFunc('Profile', 'user', 'getallactive', array('uid' => UserUtil::getVar('uid'), 'get' => 'editable'));

        // The return value of the function is checked here
        if ($items === false) {
            return LogUtil::registerError($this->__('Error! Could not load personal info items.'));
        }

        // check if we get called form the update function in case of an error
        $uname    = FormUtil::getPassedValue('uname',    (isset($args['uname']) ? $args['uname'] : null),    'GET');
        $dynadata = FormUtil::getPassedValue('dynadata', (isset($args['dynadata']) ? $args['dynadata'] : array()), 'GET');

        // merge this temporary dynadata and the errors into the items array
        foreach ($dynadata as $propattr => $propdata) {
            $items[$propattr]['temp_propdata'] = $propdata;
        }

        // Create output object
        $this->view->setCaching(false)->add_core_data();

        // Assign the items to the template
        $this->view->assign('duditems', $items)
                ->assign('uname',    (isset($uname) && !empty($uname)) ? $uname : UserUtil::getVar('uname'));

        // Return the output that has been generated by this function
        return $this->view->fetch('profile_user_modify.tpl');
    }

    /**
     * update a users profile
     *
     * @author Franky Chestnut
     */
    public function update()
    {
        // Confirm authorisation code.
        if (!SecurityUtil::confirmAuthKey()) {
            return LogUtil::registerAuthidError(ModUtil::url('Profile', 'user', 'modify'));
        }

        // Get parameters from whatever input we need.
        $uname    = FormUtil::getPassedValue('uname',    null, 'POST');
        $dynadata = FormUtil::getPassedValue('dynadata', null, 'POST');

        $uid = UserUtil::getVar('uid');

        // Check for required fields - The API function is called.
        $checkrequired = ModUtil::apiFunc('Profile', 'user', 'checkrequired', array('dynadata' => $dynadata));

        if ($checkrequired['result'] == true) {
            LogUtil::registerError($this->__f('Error! A required profile item [%s] is missing.', $checkrequired['translatedFieldsStr']));

            // we do not send the passwords here!
            $params = array('uname'    => $uname,
                    'dynadata' => $dynadata);

            return System::redirect(ModUtil::url('Profile', 'user', 'modify', $params));
        }

        // Building the sql and saving - The API function is called.
        $save = ModUtil::apiFunc('Profile', 'user', 'savedata',
                array('uid'      => $uid,
                'dynadata' => $dynadata));

        if ($save != true) {
            return System::redirect(ModUtil::url('Profile', 'user', 'view'));
        }

        // This function generated no output, we redirect the user
        LogUtil::registerStatus($this->__('Done! Saved your changes to your personal information.'));

        return System::redirect(ModUtil::url('Profile', 'user', 'view', array('uname' => UserUtil::getVar('uname'))));
    }

    /**
     * view members list
     * This function provides the main members list view
     *
     * @author Mark West
     * @return string HTML string
     */
    public function viewmembers($args)
    {
        // Security check
        if (!SecurityUtil::checkPermission('Profile:Members:', '::', ACCESS_READ)) {
            return LogUtil::registerPermissionError();
        }

        // Get parameters from whatever input we need
        $startnum  = FormUtil::getPassedValue('startnum', isset($args['startnum']) ? $args['startnum'] : null, 'GET');
        $sortby    = FormUtil::getPassedValue('sortby', isset($args['sortby']) ? $args['sortby'] : null, 'GETPOST');
        $searchby  = FormUtil::getPassedValue('searchby', isset($args['searchby']) ? $args['searchby'] : null, 'GETPOST');
        $sortorder = FormUtil::getPassedValue('sortorder', isset($args['sortorder']) ? $args['sortorder'] : null, 'GETPOST');
        $letter    = FormUtil::getPassedValue('letter', isset($args['letter']) ? $args['letter'] : null, 'GETPOST');

        // Set some defaults
        if (empty($sortby)) {
            $sortby = 'uname';
        }
        if (empty($letter)) {
            $letter = null;
        }
        if (empty($startnum)) {
            $startnum = 1;
        }

        // get some permissions to use in the cache id and later to filter template output
        if (SecurityUtil::checkPermission('Users::', '::', ACCESS_DELETE) ) {
            $edit = true;
            $delete = true;
        } elseif (SecurityUtil::checkPermission('Users::', '::', ACCESS_EDIT) ) {
            $edit = true;
            $delete = false;
        } else {
            $edit = false;
            $delete = false;
        }

        // Create output object
        $cacheid = md5((int)$edit.(int)$delete.$startnum.$letter.$sortby);
        $this->view->setCaching(true)
                        ->setCache_Id($cacheid);
        /*
    // check out if the contents are cached.
    if ($this->view->is_cached('profile_user_members_view.tpl')) {
        return $this->view->fetch('profile_user_members_view.tpl');
    }
        */
        // get the number of users to show per page from the module vars
        $itemsperpage = ModUtil::getVar('Profile', 'memberslistitemsperpage');

        // assign values for header
        $this->view->assign('memberslistreg',    ModUtil::apiFunc('Users', 'user', 'countitems')-1); // discount annonymous
        $this->view->assign('memberslistonline', ModUtil::apiFunc('Profile', 'memberslist', 'getregisteredonline'));
        $this->view->assign('memberslistnewest', UserUtil::getVar('uname', ModUtil::apiFunc('Profile', 'memberslist', 'getlatestuser')));

        $fetchargs = array('letter'    => $letter,
                'sortby'    => $sortby,
                'sortorder' => $sortorder,
                'searchby'  => $searchby,
                'startnum'  => $startnum,
                'numitems'  => $itemsperpage);

        // get full list of user id's
        $users = ModUtil::apiFunc('Profile', 'memberslist', 'getall', $fetchargs);

        $userscount = ModUtil::apiFunc('Profile', 'memberslist', 'countitems', $fetchargs);

        // Is current user online
        $this->view->assign('loggedin', UserUtil::isLoggedIn());

        // check if we should show the extra admin column
        $this->view->assign('adminedit', $edit);
        $this->view->assign('admindelete', $delete);

        foreach ($users as $userid => $user)
        {
            //$user = array_merge(UserUtil::getVars($userid['uid']), $userid);
            $isonline = ModUtil::apiFunc('Profile', 'memberslist', 'isonline', array('userid' => $userid));

            // is this user online
            $users[$userid]['onlinestatus'] = $isonline ? 1 : 0;

            // filter out any dummy url's
            if (isset($user['url']) && (!$user['url'] || in_array($user['url'], array('http://', 'http:///')))) {
                $users[$userid]['url'] = '';
            }
        }

        // get all active profile fields
        $activeduds = ModUtil::apiFunc('Profile', 'user', 'getallactive');
        foreach ($activeduds as $attr => $activedud) {
            $dudarray[$attr] = $activedud['prop_id'];
        }
        unset($activeduds);

        $this->view->assign('dudarray',  $dudarray)
                ->assign('users',     $users)
                ->assign('letter',    $letter)
                ->assign('sortby',    $sortby)
                ->assign('sortorder', $sortorder);

        // check which messaging module is available and add the necessary info
        $this->view->assign('msgmodule', ModUtil::apiFunc('Profile', 'memberslist', 'getmessagingmodule'));

        // Assign the values for the smarty plugin to produce a pager
        $this->view->assign('pager', array('numitems'     => $userscount,
                'itemsperpage' => $itemsperpage));

        // Return the output that has been generated by this function
        return $this->view->fetch('profile_user_members_view.tpl');
    }

    /**
     * Displays last X registered users
     * This function displays the last X users who registered at this site
     * available from the module.
     *
     * @author Mark West
     * @return string HTML string
     */
    public function recentmembers()
    {
        // Security check
        if (!SecurityUtil::checkPermission('Profile:Members:recent', '::', ACCESS_READ)) {
            return LogUtil::registerPermissionError();
        }

        // set the cache id
        $this->view->setCache_Id('recent' . (int)UserUtil::isLoggedIn());

        // check out if the contents are cached.
        if ($this->view->is_cached('profile_user_members_recent.tpl')) {
            return $this->view->fetch('profile_user_members_recent.tpl');
        }

        $modvars = $this->getVars();

        // get last x user id's
        $users = ModUtil::apiFunc('Profile', 'memberslist', 'getall',
                array('sortby'    => 'user_regdate',
                'numitems'  => $modvars['recentmembersitemsperpage'],
                'sortorder' => 'DESC'));

        // Is current user online
        $this->view->assign('loggedin', UserUtil::isLoggedIn());

        // assign all module vars obtained earlier
        $this->view->assign($modvars);

        // get some permissions to use in the cache id and later to filter template output
        $edit   = false;
        $delete = false;
        if (SecurityUtil::checkPermission('Users::', '::', ACCESS_DELETE) ) {
            $edit   = true;
            $delete = true;
        } elseif (SecurityUtil::checkPermission('Users::', '::', ACCESS_EDIT) ) {
            $edit = true;
        }

        // check if we should show the extra admin column
        $this->view->assign('adminedit', $edit);
        $this->view->assign('admindelete', $delete);

        foreach (array_keys($users) as $userid)
        {
            $isonline = ModUtil::apiFunc('Profile', 'memberslist', 'isonline', array('userid' => $userid));

            // display online status
            $users[$userid]['onlinestatus'] = $isonline ? 1 : 0;
        }

        $this->view->assign('users', $users);

        // check which messaging module is available and add the necessary info
        $this->view->assign('msgmodule', ModUtil::apiFunc('Profile', 'memberslist', 'getmessagingmodule'));

        // get all active profile fields
        $activeduds = ModUtil::apiFunc('Profile', 'user', 'getallactive');
        $dudarray   = array_keys($activeduds);
        unset($activeduds);

        $this->view->assign('dudarray', $dudarray);

        // Return the output that has been generated by this function
        return $this->view->fetch('profile_user_members_recent.tpl');
    }

    /**
     * View users online
     * This function displays the currently online users
     *
     * @author Mark West
     * @return string HTML string
     */
    public function onlinemembers()
    {
        // Security check
        if (!SecurityUtil::checkPermission('Profile:Members:online', '::', ACCESS_READ)) {
            return LogUtil::registerPermissionError();
        }

        // Create output object
        $this->view->setCache_Id('onlinemembers' . (int)UserUtil::isLoggedIn());

        // check out if the contents are cached.
        if ($this->view->is_cached('profile_user_members_online.tpl')) {
            return $this->view->fetch('profile_user_members_online.tpl');
        }

        // get last 10 user id's
        $users = ModUtil::apiFunc('Profile', 'memberslist', 'whosonline');

        // Current user status
        $this->view->assign('loggedin', UserUtil::isLoggedIn());

        $this->view->assign('users', $users);

        // check which messaging module is available and add the necessary info
        $this->view->assign('msgmodule', ModUtil::apiFunc('Profile', 'memberslist', 'getmessagingmodule'));

        // get all active profile fields
        $activeduds = ModUtil::apiFunc('Profile', 'user', 'getallactive');
        $dudarray   = array_keys($activeduds);
        unset($activeduds);

        $this->view->assign('dudarray', $dudarray);

        // Return the output that has been generated by this function
        return $this->view->fetch('profile_user_members_online.tpl');
    }
}
