<?php
/**
 * Zikula Application Framework
 *
 * @copyright (c), Zikula Development Team
 * @link http://www.zikula.org
 * @version $Id$
 * @license GNU/GPL - http://www.gnu.org/copyleft/gpl.html
 * @package Zikula_System_Modules
 * @subpackage Profile
 */

/**
 * the main user function
 *
 * @author Mark West
 * @return string HTML string
 */
function Profile_user_main()
{
    // showing account links is not longer part of the Profile module, redirect to Users:
    return pnRedirect(pnModURL('Users', 'user', 'main'));
}

/**
 * display item
 *
 * @author Mark West
 * @return string HTML string
 */
function Profile_user_view($args)
{
    // Security check
    if (!SecurityUtil::checkPermission('Profile::', '::', ACCESS_READ)) {
        return LogUtil::registerPermissionError();
    }

    $dom = ZLanguage::getModuleDomain('Profile');

    // Get parameters from whatever input we need.
    $uid   = (int)FormUtil::getPassedValue('uid', isset($args['uid']) ? $args['uid'] : null, 'GET');
    $uname = FormUtil::getPassedValue('uname', isset($args['uname']) ? $args['uname'] : null, 'GET');
    $page  = FormUtil::getPassedValue('page', isset($args['page']) ? $args['page'] : null, 'GET');

    // Getting uid by uname
    if (!empty($uname)) {
        $uid = pnUserGetIDFromName($uname);
    } elseif (empty($uid)) {
        $uid = pnUserGetVar('uid');
    }

    // Check for an invalid uid (uid = 1 is the anonymous user)
    if ($uid < 2) {
        return LogUtil::registerError(__('Error! Could not find this user.', $dom), 404);
    }

    // Get all the user data
    $userinfo = pnUserGetVars($uid);

    if (!$userinfo) {
        return LogUtil::registerError(__('Error! Could not find this user.', $dom), 404);
    }

    // Check if the user is watching its own profile or if he is admin
    // TODO maybe remove the four lines below
    $currentuser = pnUserGetVar('uid');
    $ismember    = ($currentuser >= 2);
    $isowner     = ($currentuser == $uid);
    $isadmin     = SecurityUtil::checkPermission('Profile::', '::', ACCESS_ADMIN);

    // Get all active profile fields
    $activeduds = pnModAPIFunc('Profile', 'user', 'getallactive',
                               array('get' => 'viewable',
                                     'uid' => $uid));

    // Fill the DUD values array
    $dudarray = array();
    foreach (array_keys($activeduds) as $dudattr) {
        $dudarray[$dudattr] = isset($userinfo['__ATTRIBUTES__'][$dudattr]) ? $userinfo['__ATTRIBUTES__'][$dudattr] : '';
    }

    // Create output object
    $render = & pnRender::getInstance('Profile', false, null, true);

    $render->assign('dudarray', $dudarray);
    $render->assign('fields',   $activeduds);

    $render->assign('uid',      $userinfo['uid']);
    $render->assign('uname',    $userinfo['uname']);
    $render->assign('userinfo', $userinfo);

    $render->assign('ismember', $ismember);
    $render->assign('isadmin',  $isadmin);
    $render->assign('sameuser', $isowner);

    // Return the output that has been generated by this function
    if (!empty($page)) {
        if ($render->template_exists("profile_user_view_{$page}.htm")) {
            return $render->fetch("profile_user_view_{$page}.htm", $uid);
        } else {
            return LogUtil::registerError(__f('Error! Could not find profile page [%s].', DataUtil::formatForDisplay($page), $dom), 404);
        }
    }

    return $render->fetch('profile_user_view.htm', $uid);
}

/**
 * modify a users profile information
 *
 * @author Franky Chestnut
 */
function Profile_user_modify($args)
{
    // Security check
    if (!pnUserLoggedIn() || !SecurityUtil::checkPermission('Profile::', '::', ACCESS_READ)) {
        return LogUtil::registerPermissionError();
    }

    $dom = ZLanguage::getModuleDomain('Profile');

    // check if we get called form the update function in case of an error
    $uname    = FormUtil::getPassedValue('uname',    (isset($args['uname']) ? $args['uname'] : null),    'GET');
    $dynadata = FormUtil::getPassedValue('dynadata', (isset($args['dynadata']) ? $args['dynadata'] : array()), 'GET');

    // Getting uid by uname
    if (!empty($uname)) {
        $uid = pnUserGetIDFromName($uname);
    } elseif (empty($uid)) {
        $uid = pnUserGetVar('uid');
    }

    // The API function is called.
    $items = pnModAPIFunc('Profile', 'user', 'getallactive', array('get' => 'editable', 'uid' => $uid));

    // The return value of the function is checked here
    if ($items === false) {
        return LogUtil::registerError(__('Error! Could not load personal info items.', $dom));
    }

    // merge this temporary dynadata and the errors into the items array
    foreach ($dynadata as $propattr => $propdata) {
        $items[$propattr]['temp_propdata'] = $propdata;
    }

    // Create output object
    $render = & pnRender::getInstance('Profile', false, null, true);

    // Assign the items to the template
    $render->assign('duditems', $items);
    $render->assign('uname',    (isset($uname) && !empty($uname)) ? $uname : pnUserGetVar('uname'));

    // Return the output that has been generated by this function
    return $render->fetch('profile_user_modify.htm');
}

/**
 * update a users profile
 *
 * @author Franky Chestnut
 */
function Profile_user_update()
{
    // Confirm authorisation code.
    if (!SecurityUtil::confirmAuthKey()) {
        return LogUtil::registerAuthidError(pnModURL('Profile', 'user', 'modify'));
    }

    $dom = ZLanguage::getModuleDomain('Profile');

    // Get parameters from whatever input we need.
    $uname    = FormUtil::getPassedValue('uname',    null, 'POST');
    $dynadata = FormUtil::getPassedValue('dynadata', null, 'POST');

    $uid = pnUserGetVar('uid');

    // Check for required fields - The API function is called.
    $checkrequired = pnModAPIFunc('Profile', 'user', 'checkrequired', array('dynadata' => $dynadata));

    if ($checkrequired['result'] == true) {
        LogUtil::registerError(__f('Error! A required profile item [%s] is missing.', $checkrequired['translatedFieldsStr'], $dom));

        // we do not send the passwords here!
        $params = array('uname'    => $uname,
                        'dynadata' => $dynadata);

        return pnRedirect(pnModURL('Profile', 'user', 'modify', $params));
    }

    // Building the sql and saving - The API function is called.
    $save = pnModAPIFunc('Profile', 'user', 'savedata',
                         array('uid'      => $uid,
                               'dynadata' => $dynadata));

    if ($save != true) {
        return pnRedirect(pnModUrl('Profile', 'user', 'view'));
    }

    // This function generated no output, we redirect the user
    LogUtil::registerStatus(__('Done! Saved your changes to your personal information.', $dom));

    return pnRedirect(pnModUrl('Profile', 'user', 'view', array('uname' => pnUserGetVar('uname'))));
}

/**
 * view members list
 * This function provides the main members list view
 *
 * @author Mark West
 * @return string HTML string
 */
function Profile_user_viewmembers($args)
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
    $render  = & pnRender::getInstance('Profile', true, $cacheid);
/*
    // check out if the contents are cached.
    if ($render->is_cached('profile_user_members_view.htm')) {
        return $render->fetch('profile_user_members_view.htm');
    }
*/
    // get the number of users to show per page from the module vars
    $itemsperpage = pnModGetVar('Profile', 'memberslistitemsperpage');

    // assign values for header
    $render->assign('memberslistreg',    pnModAPIFunc('Users', 'user', 'countitems')-1); // discount annonymous
    $render->assign('memberslistonline', pnModAPIFunc('Profile', 'memberslist', 'getregisteredonline'));
    $render->assign('memberslistnewest', pnUserGetVar('uname', pnModAPIFunc('Profile', 'memberslist', 'getlatestuser')));

    $fetchargs = array('letter'    => $letter,
                       'sortby'    => $sortby,
                       'sortorder' => $sortorder,
                       'searchby'  => $searchby,
                       'startnum'  => $startnum,
                       'numitems'  => $itemsperpage);

    // get full list of user id's
    $users = pnModAPIFunc('Profile', 'memberslist', 'getall', $fetchargs);

    $userscount = pnModAPIFunc('Profile', 'memberslist', 'countitems', $fetchargs);

    // Is current user online
    $render->assign('loggedin', pnUserLoggedIn());

    // check if we should show the extra admin column
    $render->assign('adminedit', $edit);
    $render->assign('admindelete', $delete);

    foreach ($users as $userid => $user)
    {
        //$user = array_merge(pnUserGetVars($userid['uid']), $userid);
        $isonline = pnModAPIFunc('Profile', 'memberslist', 'isonline', array('userid' => $userid));

        // is this user online
        $users[$userid]['onlinestatus'] = $isonline ? 1 : 0;

        // filter out any dummy url's
        if (isset($user['url']) && (!$user['url'] || in_array($user['url'], array('http://', 'http:///')))) {
            $users[$userid]['url'] = '';
        }
    }

    // get all active profile fields
    $activeduds = pnModAPIfunc('Profile', 'user', 'getallactive');
    foreach ($activeduds as $attr => $activedud) {
        $dudarray[$attr] = $activedud['prop_id'];
    }
    unset($activeduds);

    $render->assign('dudarray',  $dudarray);
    $render->assign('users',     $users);
    $render->assign('letter',    $letter);
    $render->assign('sortby',    $sortby);
    $render->assign('sortorder', $sortorder);

    // check which messaging module is available and add the necessary info
    $render->assign('msgmodule', pnModAPIFunc('Profile', 'memberslist', 'getmessagingmodule'));

    // Assign the values for the smarty plugin to produce a pager
    $render->assign('pager', array('numitems'     => $userscount,
                                   'itemsperpage' => $itemsperpage));

    // Return the output that has been generated by this function
    return $render->fetch('profile_user_members_view.htm');
}

/**
 * Displays last X registered users
 * This function displays the last X users who registered at this site
 * available from the module.
 *
 * @author Mark West
 * @return string HTML string
 */
function Profile_user_recentmembers()
{
    // Security check
    if (!SecurityUtil::checkPermission('Profile:Members:recent', '::', ACCESS_READ)) {
        return LogUtil::registerPermissionError();
    }

    // Create output object
    $render = & pnRender::getInstance('Profile');

    // set the cache id
    $render->cache_id = 'recent' . pnUserLoggedIn();

    // check out if the contents are cached.
    if ($render->is_cached('profile_user_members_recent.htm')) {
        return $render->fetch('profile_user_members_recent.htm');
    }

    $modvars = pnModGetVar('Profile');

    // get last x user id's
    $users = pnModAPIFunc('Profile', 'memberslist', 'getall',
                          array('sortby'    => 'user_regdate',
                                'numitems'  => $modvars['recentmembersitemsperpage'],
                                'sortorder' => 'DESC'));

    // Is current user online
    $render->assign('loggedin', pnUserLoggedIn());

    // assign all module vars obtained earlier
    $render->assign($modvars);

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
    $render->assign('adminedit', $edit);
    $render->assign('admindelete', $delete);

    foreach (array_keys($users) as $userid)
    {
        $isonline = pnModAPIFunc('Profile', 'memberslist', 'isonline', array('userid' => $userid));

        // display online status
        $users[$userid]['onlinestatus'] = $isonline ? 1 : 0;
    }

    $render->assign('users', $users);

    // check which messaging module is available and add the necessary info
    $render->assign('msgmodule', pnModAPIFunc('Profile', 'memberslist', 'getmessagingmodule'));

    // get all active profile fields
    $activeduds = pnModAPIfunc('Profile', 'user', 'getallactive');
    $dudarray   = array_keys($activeduds);
    unset($activeduds);

    $render->assign('dudarray', $dudarray);

    // Return the output that has been generated by this function
    return $render->fetch('profile_user_members_recent.htm');
}

/**
 * View users online
 * This function displays the currently online users
 *
 * @author Mark West
 * @return string HTML string
 */
function Profile_user_onlinemembers()
{
    // Security check
    if (!SecurityUtil::checkPermission('Profile:Members:online', '::', ACCESS_READ)) {
        return LogUtil::registerPermissionError();
    }

    // Create output object
    $render = & pnRender::getInstance('Profile');

    // set the cache id
    $render->cache_id = 'onlinemembers' . pnUserLoggedIn();

    // check out if the contents are cached.
    if ($render->is_cached('profile_user_members_online.htm')) {
       return $render->fetch('profile_user_members_online.htm');
    }

    // get last 10 user id's
    $users = pnModAPIFunc('Profile', 'memberslist', 'whosonline');

    // Current user status
    $render->assign('loggedin', pnUserLoggedIn());

    $render->assign('users', $users);

    // check which messaging module is available and add the necessary info
    $render->assign('msgmodule', pnModAPIFunc('Profile', 'memberslist', 'getmessagingmodule'));

    // get all active profile fields
    $activeduds = pnModAPIfunc('Profile', 'user', 'getallactive');
    $dudarray   = array_keys($activeduds);
    unset($activeduds);

    $render->assign('dudarray', $dudarray);

    // Return the output that has been generated by this function
    return $render->fetch('profile_user_members_online.htm');
}
