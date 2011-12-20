<?php
/**
 * Controller for when user maps gadget user id to Member id
 */

class GadgetAuthorisation extends Page_Controller {

    public static $url_segment = '';

    /**
     * @var array The allowed actions for this controller
     */
    public static $allowed_actions = array ('authForm');

    /**
     * Initialize the controller and include dependencies
     */
    public function init() {
        parent::init();
        if (!Member::logged_in_session_exists()) {
            Director::redirect("Security/login?BackURL=" . urlencode($_SERVER['REQUEST_URI']));
        }
    }

    /**
     * Set the url for this controller and register it with {@link Director}
     * @param string $url The URL to use
     * @param $priority The priority of the URL rule
     */
    public static function set_url($url, $priority = 50) {
        self::$url_segment = $url;
        Director::addRules($priority,array(
            $url . '/$Action/$ID' => 'GadgetAuthorisation'
        ));
    }

    /**
     * Creates an absolute link to this page for authorisation purposes
     * @param array $params
     */
    public static function returnAuthLink($params = array()) {
        return self::BaseHref() . ''. self::$url_segment .'/index/?' . http_build_query($params);
    }

    /**
     * Shows a page to authorise mapping of gadget to Member
     */
    public function index() {
        //Verify token
        $token = (string) $this->request->getVar('token');
        $ownerid = (string) $this->request->getVar('owner');
        $container = (string) $this->request->getVar('container');
        $site = (string) strip_tags($this->request->getVar('site'));
        //Check params not altered
        if (snapp_security::create_mapuser_token($ownerid, $container) !== $token) {
            $this->response->setStatusCode(403);
            return array('message' => _t('GADGET.AUTHFAIL', 'Incorrect details sent from gadget.'));
        }
        //Check if mapping exists - if so try and close window
        $containerid = GadgetContainer::get_containerid_fromname($container);
        //Check container exists, check container is enabled
        if (!$containerid || !GadgetContainer::iscontainer_name_enabled($container)) {
            //problem getting container record from DB
            return array('message' => _t('GADGET.AUTHFAIL_NE',
                'Use of this network has been disabled by the system administrator.'));
        }

        //Double check user is not mapped already
        $existsalready = GadgetMapping::user_map_exists($ownerid, $containerid);

        if ($existsalready) {
            return $this->closewin();
        }

        //Return content and form to make authorisation
        $info = _t('GADGETS.AUTHinfo', "By authorising this gadget to access your account, you will be
 able to use it on <em>$</em>.
<br/><br/>
Information shared with the gadget will not be stored on <em>$</em> and other gadgets will not have access to it.
<br/<br/>
Gadgets do not have access to your password or other sensitive information in your account.
");
        $info = str_replace('$', $site, $info);
        $form = $this->authForm($token, $ownerid, $container);
        return array('infotext' => $info, 'aForm' => $form);
    }

    /**
     * Authorisation form (hidden fields & action buttons)
     * @param string $token
     * @param string $ownerid
     * @param string $container
     */
    public function authForm($token = null, $ownerid = null, $container = null) {
        $fields = new HiddenFieldSet(array(
            new HiddenField('token', '', $token),
            new HiddenField('ownerid', '', $ownerid),
            new HiddenField('container', '', $container),
            )
        );

        $actions = new FieldSet(array(
            new FormAction('makeMap', _t('GADGETAUTH.submit', 'Authorise access')),
            new FormAction('cancelMap', _t('GADGETAUTH.cancel', 'Deny access')),
        ));

        $authform = new Form($this, 'authForm', $fields, $actions);
        return $authform;
    }

    /**
     * Make mapping on Form submit
     * @param array $data
     * @param Form $form
     */
    public function makeMap($data, $form) {
        //check params not altered
        if (snapp_security::create_mapuser_token($data['ownerid'], $data['container']) !== $data['token']) {
            $this->response->setStatusCode(403);
            return array('message' => _t('GADGET.AUTHFAIL', 'Incorrect details sent from gadget.'));
        }

        if (!GadgetMapping::create_mapping($data['ownerid'], $data['container'],
            Member::currentUserID())) {
            return array('message' => _t('GADGET.AUTHerror', 'Error creating authorisation record.'));
        }
        return $this->closeWin();
    }

    /**
     * Form cancel
     * @param array $data
     * @param Form $form
     */
    public function cancelMap($data, $form) {
        return $this->closeWin();
    }

    /**
     * Returns flag to close the authorisation window to template
     */
    public function closeWin() {
        return array('closewindow' => true);
    }
}
