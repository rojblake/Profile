<?php
/**
 * Copyright Zikula Foundation 2009 - Zikula Application Framework
 *
 * This work is contributed to the Zikula Foundation under one or more
 * Contributor Agreements and licensed to You under the following license:
 *
 * @license GNU/LGPLv3 (or at your option, any later version).
 * @package Profile
 *
 * Please see the NOTICE file distributed with this source code for further
 * information regarding copyright and licensing.
 */

/**
 * UI operations executable by general users.
 */

namespace Zikula\Module\ProfileModule\Controller;

use DataUtil;
use ModUtil;
use SecurityUtil;
use Symfony\Component\HttpFoundation\RedirectResponse;
use System;
use UserUtil;
use Zikula\Core\Event\GenericEvent;

class UserController extends \Zikula_AbstractController
{
    /**
     * The default entry point.
     *
     * This redirects back to the default entry point for the Users module.
     *
     * @return RedirectResponse
     */
    public function mainAction()
    {
        return new RedirectResponse(System::normalizeUrl(ModUtil::url($this->name, 'user', 'viewmembers')), 301);
    }

    public function indexAction()
    {
        return new RedirectResponse(System::normalizeUrl(ModUtil::url($this->name, 'user', 'viewmembers')), 301);
    }

    /**
     * Display item.
     *
     * Parameters passed via the $args array, or via GET:
     * --------------------------------------------------
     * numeric uid   The user account id (uid) of the user for whom to display profile information; optional, ignored if uname is supplied, if not provided
     *                  and if uname is not supplied then defaults to the current user.
     * string  uname The user name of the user for whom to display profile information; optional, if not supplied, then uid is used to determine the user.
     * string  page  The name of the Profile "page" (view template) to display; optional, if not provided then the standard view template is used.
     *
     * @param array $args All parameters passed to this function via an internal call.
     *
     * @return RedirectResponse|string The rendered template output.
     *
     * @throws AccessDeniedException on failed permission check
     */
    public function viewAction($args)
    {
        // Security check
        if (!SecurityUtil::checkPermission($this->name.'::view', '::', ACCESS_READ)) {
            throw new AccessDeniedException();
        }
        // Get parameters from whatever input we need.
        $uid = (int)$this->request->query->get('uid', isset($args['uid']) ? $args['uid'] : null);
        $uname = $this->request->query->get('uname', isset($args['uname']) ? $args['uname'] : null);
        $page = $this->request->query->get('page', isset($args['page']) ? $args['page'] : null);
        // Getting uid by uname
        if (!empty($uname)) {
            $uid = UserUtil::getIdFromName($uname);
        } elseif (empty($uid)) {
            $uid = UserUtil::getVar('uid');
        }
        // Check for an invalid uid (uid = 1 is the anonymous user)
        if ($uid < 2) {
            $this->request->getSession()->getFlashBag()->add('error', $this->__('Error! Could not find this user.'));
            return new RedirectResponse(System::normalizeUrl(ModUtil::url($this->name, 'user', 'viewmembers')));
        }
        // Get all the user data
        $userinfo = UserUtil::getVars($uid);
        if (!$userinfo) {
            $this->request->getSession()->getFlashBag()->add('error', $this->__('Error! Could not find this user.'));
            return new RedirectResponse(System::normalizeUrl(ModUtil::url($this->name, 'user', 'viewmembers')));
        }
        // Check if the user is watching its own profile or if he is admin
        // TODO maybe remove the four lines below
        $currentuser = UserUtil::getVar('uid');
        $ismember = $currentuser >= 2;
        $isowner = $currentuser == $uid;
        $isadmin = SecurityUtil::checkPermission($this->name.'::', '::', ACCESS_ADMIN);
        // Get all active profile fields
        $activeduds = ModUtil::apiFunc($this->name, 'user', 'getallactive', array('get' => 'viewable', 'uid' => $uid));
        $fieldsets = array();
        $items = $activeduds;
        foreach ($items as $propattr => $propdata) {
            $items[$propattr]['prop_fieldset'] = ((isset($items[$propattr]['prop_fieldset'])) && (!empty($items[$propattr]['prop_fieldset']))) ? $items[$propattr]['prop_fieldset'] : $this->__('User Information');
            $fieldsets[DataUtil::formatPermalink($items[$propattr]['prop_fieldset'])] = $items[$propattr]['prop_fieldset'];
        }
        $activeduds = $items;
        // Fill the DUD values array
        $dudarray = array();
        foreach (array_keys($activeduds) as $dudattr) {
            $dudarray[$dudattr] = isset($userinfo['__ATTRIBUTES__'][$dudattr]) ? $userinfo['__ATTRIBUTES__'][$dudattr] : '';
        }
        // Create output object
        $this->view->setCaching(false);
        $this->view->assign('dudarray', $dudarray)
            ->assign('fields', $activeduds)
            ->assign('fieldsets', $fieldsets)
            ->assign('uid', $userinfo['uid'])
            ->assign('uname', $userinfo['uname'])
            ->assign('userinfo', $userinfo)
            ->assign('ismember', $ismember)
            ->assign('isadmin', $isadmin)
            ->assign('sameuser', $isowner);
        // Return the output that has been generated by this function
        if (!empty($page)) {
            if ($this->view->template_exists("User/view_{$page}.tpl")) {
                return $this->view->fetch("User/view_{$page}.tpl", $uid);
            } else {
                $this->request->getSession()->getFlashBag()->add('error', $this->__f('Error! Could not find profile page [%s].', DataUtil::formatForDisplay($page)));
                return new RedirectResponse(System::normalizeUrl(ModUtil::url($this->name, 'user', 'viewmembers')));
            }
        }
        return $this->response($this->view->fetch('User/view.tpl', $uid));
    }

    /**
     * Modify a users profile information.
     *
     * Parameters passed via the $args array, or via GET:
     * --------------------------------------------------
     * string   uname The user name of the account for which profile information should be modified; defaults to the uname of the current user.
     * dynadata array The modified profile information passed into this function in case of an error in the update function.
     *
     * @param array $args All parameters passed to this function via an internal call.
     *
     * @return RedirectResponse|string The rendered template output.
     *
     * @throws AccessDeniedException on failed permission check
     */
    public function modifyAction($args)
    {
        // Security check
        if (!UserUtil::isLoggedIn() || !SecurityUtil::checkPermission($this->name.'::', '::', ACCESS_READ)) {
            throw new AccessDeniedException();
        }
        // The API function is called.
        $items = ModUtil::apiFunc($this->name, 'user', 'getallactive', array('uid' => UserUtil::getVar('uid'), 'get' => 'editable'));
        // The return value of the function is checked here
        if ($items === false) {
            $this->request->getSession()->getFlashBag()->add('error', $this->__('Error! Could not load items.'));
            return new RedirectResponse(System::normalizeUrl(ModUtil::url($this->name, 'user', 'viewmembers')));
        }
        // check if we get called form the update function in case of an error
        $uname = $this->request->query->get('uname', isset($args['uname']) ? $args['uname'] : null);
        $dynadata = $this->request->query->get('dynadata', isset($args['dynadata']) ? $args['dynadata'] : array());
        $fieldsets = array();
        foreach ($items as $propattr => $propdata) {
            $items[$propattr]['prop_fieldset'] = ((isset($items[$propattr]['prop_fieldset'])) && (!empty($items[$propattr]['prop_fieldset']))) ? $items[$propattr]['prop_fieldset'] : $this->__('User Information');
            $fieldsets[DataUtil::formatPermalink($items[$propattr]['prop_fieldset'])] = $items[$propattr]['prop_fieldset'];
        }
        // merge this temporary dynadata and the errors into the items array
        foreach ($dynadata as $propattr => $propdata) {
            $items[$propattr]['temp_propdata'] = $propdata;
        }
        // Create output object
        $this->view->setCaching(false);
        // Assign the items to the template
        $this->view->assign('duditems', $items)->assign('fieldsets', $fieldsets)->assign('uname', isset($uname) && !empty($uname) ? $uname : UserUtil::getVar('uname'));
        // Return the output that has been generated by this function
        return $this->response($this->view->fetch('User/modify.tpl'));
    }

    /**
     * Update a users profile.
     *
     * Parameters passed via POST:
     * ---------------------------
     * string uname    The user name of the account for which profile information should be updated.
     * array  dynadata An array containing the updated profile information for the user account.
     *
     * @return RedirectResponse
     *
     * @throws Zikula_Exception_Forbidden Thrown if the csrftoken is not confirmed.
     */
    public function updateAction()
    {
        $this->checkCsrfToken();
        $uid = UserUtil::getVar('uid');
        $user = UserUtil::getVars($uid);
        $dynadata = $this->request->request->get('dynadata', null);
        $event_args = array('uid' => $uid, 'dynadata' => $dynadata);
        $event = new GenericEvent($user, $event_args);
        $event = $this->getDispatcher()->dispatch('module.profile.update', $event);
        // Get parameters from whatever input we need.
        $uname = $this->request->request->get('uname', null);
        /**
         * Set $dynadata again, in case it has been modified by
         * a persistent module handler.
         */
        $dynadata = $this->request->request->get('dynadata', null);
        // Check for required fields - The API function is called.
        $checkrequired = ModUtil::apiFunc($this->name, 'user', 'checkrequired', array('dynadata' => $dynadata));
        if ($checkrequired['result'] == true) {
            $this->request->getSession()->getFlashBag()->add('error', $this->__f('Error! A required profile item [%s] is missing.', $checkrequired['translatedFieldsStr']));
            // we do not send the passwords here!
            $params = array('uname' => $uname, 'dynadata' => $dynadata);
            return new RedirectResponse(System::normalizeUrl(ModUtil::url($this->name, 'user', 'modify', $params)));
        }
        // Building the sql and saving - The API function is called.
        $save = ModUtil::apiFunc($this->name, 'user', 'savedata', array('uid' => $uid, 'dynadata' => $dynadata));
        if ($save != true) {
            return new RedirectResponse(System::normalizeUrl(ModUtil::url($this->name, 'user', 'view')));
        }
        // This function generated no output, we redirect the user
        $this->request->getSession()->getFlashBag()->add('status', $this->__('Done! Saved your changes to your personal information.'));
        return new RedirectResponse(System::normalizeUrl(ModUtil::url($this->name, 'user', 'view', array('uname' => UserUtil::getVar('uname')))));
    }

    /**
     * View members list.
     *
     * This function provides the main members list view.
     *
     * Parameters passed via the $args array, or via GET, or via POST:
     * ---------------------------------------------------------------
     * numeric startnum The ordinal number of the record at which to begin displaying records; not obtained via POST.
     * string  sortby    A comma-separated list of fields on which the list of members should be sorted.
     * mixed   searchby  Selection criteria for the query that retrieves the member list; one of 'uname' to select by user name, 'all' to select on all
     *                      available dynamic user data properites, a numeric value indicating the property id of the property on which to select,
     *                      an array indexed by property id containing values for each property on which to select, or a string containing the name of
     *                      a property on which to select.
     * string  sortorder One of 'ASC' or 'DESC' indicating whether sorting should be in ascending order or descending order.
     * string  letter    If searchby is 'uname' then either a letter on which to match the beginning of a user name or a non-letter indicating that
     *                      selection should include user names beginning with numbers and/or other symbols, if searchby is a numeric propery id or
     *                      is a string containing the name of a property then the string on which to match the begining of the value for that property.
     *
     * @param array $args All parameters passed to this function via an internal call.
     *
     * @return string The rendered template output.
     *
     * @throws AccessDeniedException on failed permission check
     */
    public function viewmembersAction($args)
    {
        // Security check
        if (!SecurityUtil::checkPermission($this->name.':Members:', '::', ACCESS_READ)) {
            throw new AccessDeniedException();
        }
        // Get parameters from whatever input we need
        $startnum = $this->request->query->get('startnum', isset($args['startnum']) ? $args['startnum'] : null);
        $sortby = $this->request->query->get('sortby', $this->request->request->get('sortby', isset($args['sortby']) ? $args['sortby'] : null));
        $searchby = $this->request->query->get('searchby', $this->request->request->get('searchby', isset($args['searchby']) ? $args['searchby'] : null));
        $sortorder = $this->request->query->get('sortorder', $this->request->request->get('sortorder', isset($args['sortorder']) ? $args['sortorder'] : null));
        $letter = $this->request->query->get('letter', $this->request->request->get('letter', isset($args['letter']) ? $args['letter'] : null));
        // Set some defaults
        if (empty($sortby)) {
            $sortby = 'uname';
        }
        if (empty($letter)) {
            $letter = null;
        }
        if (empty($startnum)) {
            $startnum = -1;
        }
        // get some permissions to use in the cache id and later to filter template output
        if (SecurityUtil::checkPermission('Users::', '::', ACCESS_DELETE)) {
            $edit = true;
            $delete = true;
        } elseif (SecurityUtil::checkPermission('Users::', '::', ACCESS_EDIT)) {
            $edit = true;
            $delete = false;
        } else {
            $edit = false;
            $delete = false;
        }
        // Create output object
        $cacheid = md5((int)$edit . (int)$delete . $startnum . $letter . $sortby);
        $this->view->setCaching(true)->setCacheId($cacheid);
        // get the number of users to show per page from the module vars
        $itemsperpage = ModUtil::getVar($this->name, 'memberslistitemsperpage');
        // assign values for header
        $this->view->assign('memberslistreg', ModUtil::apiFunc('Users', 'user', 'countitems') - 1);
        // discount annonymous
        $this->view->assign('memberslistonline', ModUtil::apiFunc($this->name, 'memberslist', 'getregisteredonline'));
        $this->view->assign('memberslistnewest', UserUtil::getVar('uname', ModUtil::apiFunc($this->name, 'memberslist', 'getlatestuser')));
        $fetchargs = array(
            'letter' => $letter,
            'sortby' => $sortby,
            'sortorder' => $sortorder,
            'searchby' => $searchby,
            'startnum' => $startnum,
            'numitems' => $itemsperpage,
            'returnUids' => false);
        // get full list of user id's
        $users = ModUtil::apiFunc($this->name, 'memberslist', 'getall', $fetchargs);
        $userscount = ModUtil::apiFunc($this->name, 'memberslist', 'countitems', $fetchargs);
        // Is current user online
        $this->view->assign('loggedin', UserUtil::isLoggedIn());
        // check if we should show the extra admin column
        $this->view->assign('adminedit', $edit);
        $this->view->assign('admindelete', $delete);
        foreach ($users as $userid => $user) {
            //$user = array_merge(UserUtil::getVars($userid['uid']), $userid);
            $isonline = ModUtil::apiFunc($this->name, 'memberslist', 'isonline', array('userid' => $userid));
            // is this user online
            $users[$userid]['onlinestatus'] = $isonline ? 1 : 0;
            // filter out any dummy url's
            if (isset($user['url']) && (!$user['url'] || in_array($user['url'], array('http://', 'http:///')))) {
                $users[$userid]['url'] = '';
            }
        }
        // get all active profile fields
        $activeduds = ModUtil::apiFunc($this->name, 'user', 'getallactive');
        foreach ($activeduds as $attr => $activedud) {
            $dudarray[$attr] = $activedud['prop_id'];
        }
        unset($activeduds);
        $this->view->assign('dudarray', $dudarray)
            ->assign('users', $users)
            ->assign('letter', $letter)
            ->assign('sortby', $sortby)
            ->assign('sortorder', $sortorder);
        // check which messaging module is available and add the necessary info
        $this->view->assign('msgmodule', ModUtil::apiFunc($this->name, 'memberslist', 'getmessagingmodule'));
        // Assign the values for the smarty plugin to produce a pager
        $this->view->assign('pager', array('numitems' => $userscount, 'itemsperpage' => $itemsperpage));
        // Return the output that has been generated by this function
        return $this->response($this->view->fetch('User/members_view.tpl'));
    }

    /**
     * Displays last X registered users.
     *
     * This function displays the last X users who registered at this site available from the module.
     *
     * @return string The rendered template output.
     *
     * @throws AccessDeniedException on failed permission check
     */
    public function recentmembersAction()
    {
        // Security check
        if (!SecurityUtil::checkPermission($this->name.':Members:recent', '::', ACCESS_READ)) {
            throw new AccessDeniedException();
        }
        // set the cache id
        $this->view->setCacheId('recent' . (int)UserUtil::isLoggedIn());
        // check out if the contents are cached.
        if ($this->view->is_cached('User/members_recent.tpl')) {
            return $this->view->fetch('User/members_recent.tpl');
        }
        $modvars = $this->getVars();
        // get last x user id's
        $users = ModUtil::apiFunc($this->name, 'memberslist', 'getall', array(
            'sortby' => 'user_regdate',
            'numitems' => $modvars['recentmembersitemsperpage'],
            'sortorder' => 'DESC',
            'returnUids' => false));
        // Is current user online
        $this->view->assign('loggedin', UserUtil::isLoggedIn());
        // assign all module vars obtained earlier
        $this->view->assign($modvars);
        // get some permissions to use in the cache id and later to filter template output
        $edit = false;
        $delete = false;
        if (SecurityUtil::checkPermission('Users::', '::', ACCESS_DELETE)) {
            $edit = true;
            $delete = true;
        } elseif (SecurityUtil::checkPermission('Users::', '::', ACCESS_EDIT)) {
            $edit = true;
        }
        // check if we should show the extra admin column
        $this->view->assign('adminedit', $edit);
        $this->view->assign('admindelete', $delete);
        foreach (array_keys($users) as $userid) {
            $isonline = ModUtil::apiFunc($this->name, 'memberslist', 'isonline', array('userid' => $userid));
            // display online status
            $users[$userid]['onlinestatus'] = $isonline ? 1 : 0;
        }
        $this->view->assign('users', $users);
        // check which messaging module is available and add the necessary info
        $this->view->assign('msgmodule', ModUtil::apiFunc($this->name, 'memberslist', 'getmessagingmodule'));
        // get all active profile fields
        $activeduds = ModUtil::apiFunc($this->name, 'user', 'getallactive');
        $dudarray = array_keys($activeduds);
        unset($activeduds);
        $this->view->assign('dudarray', $dudarray);
        // Return the output that has been generated by this function
        return $this->response($this->view->fetch('User/members_recent.tpl'));
    }

    /**
     * View users online.
     *
     * This function displays the currently online users.
     *
     * @return string The rendered template output.
     *
     * @throws AccessDeniedException on failed permission check
     */
    public function onlinemembersAction()
    {
        // Security check
        if (!SecurityUtil::checkPermission($this->name.':Members:online', '::', ACCESS_READ)) {
            throw new AccessDeniedException();
        }
        // Create output object
        $this->view->setCacheId('onlinemembers' . (int)UserUtil::isLoggedIn());
        // check out if the contents are cached.
        if ($this->view->is_cached('User/members_online.tpl')) {
            return $this->view->fetch('User/members_online.tpl');
        }
        // get last 10 user id's
        $users = ModUtil::apiFunc($this->name, 'memberslist', 'whosonline');
        // Current user status
        $this->view->assign('loggedin', UserUtil::isLoggedIn());
        $this->view->assign('users', $users);
        // check which messaging module is available and add the necessary info
        $this->view->assign('msgmodule', ModUtil::apiFunc($this->name, 'memberslist', 'getmessagingmodule'));
        // get all active profile fields
        $activeduds = ModUtil::apiFunc($this->name, 'user', 'getallactive');
        $dudarray = array_keys($activeduds);
        unset($activeduds);
        $this->view->assign('dudarray', $dudarray);
        // Return the output that has been generated by this function
        return $this->response($this->view->fetch('User/members_online.tpl'));
    }

}
