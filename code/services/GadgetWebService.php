<?php

/**
 * Web service methods to support gadgets
 */
class GadgetWebService implements WebServiceable {

    /**
     * Something that silverstripe imposes!
     */
    public function __construct() {

    }

    public function webEnabledMethods() {
        return array(
            'mapUser' => 'GET',
        );
    }

    /**
     * Checks container, and ownerid to see if there is a mapping
     * If not, info to Authorisation screen is sent
     */
    public function mapUser() {
        //Check if user exists
        if (!is_null(GadgetWebServiceController::getGadgetMemberID())) {
            return array('userexists' => true);
        }
        //If user doesn't exist send info back in response
        //(pick up details from request vars)
        $rvars = GadgetWebServiceController::getRequestVars();
        $ownerid = snapp_security::get_os_ownerid($rvars);
        $viewerid = snapp_security::get_os_viewerid($rvars);
        $container = snapp_security::get_os_container($rvars);

        $json = array();
        $json['userexists'] = false;
        //if owner is viewer then create auth token and send url so they can login and create mapping
        if ($ownerid !== $viewerid) {
            //In theory could slip past here is owner and viewer are false, but that shouldn't happen as reuqest would then not validate
            return array('message' => 'Not allowed to authorise gadget.');
        }
        //If user is not signed in (id -1) then send message
        if ($ownerid == -1 || $viewerid == -1 || (!$ownerid && !$viewerid)) {
            $json['instructions'] = _t('GADGET.needsignin', 'You must sign in to authorise this gadget.');
        } else {
            $tokenstr = snapp_security::create_mapuser_token($ownerid, $container);
            $params = array();
            $params['token'] = $tokenstr;
            $params['owner'] = $ownerid;
            $params['container'] = $container;
            $params['site'] = snapp_security::get_os_container_name($rvars);
            $url = GadgetAuthorisation::returnAuthLink($params);
            $json['url'] = $url;
            $json['instructions'] = _t('GADGET.AuthInstruct', 'To use this gadget, you need to authorise access to your account. To do this go to the ');
            $json['linktext'] = _t('GADGET.linktext', 'authorisation page');
        }
        return $json;
    }

    /**
     * Will check if container/owner id user is mapped against a Member
     * in GadgetMapping table
     * @param $requestvars array OS request
     * @return Int | null Member ID
     */
    public static function getUserMapping($requestvars) {
        $container = snapp_security::get_os_container($requestvars);
        $containerid = GadgetContainer::get_containerid_fromname($container);

        if (!$containerid) {
            //problem getting container record from DB
            return null;
        }

        //Get owner and viewer info
        $ownerid = snapp_security::get_os_ownerid($requestvars);
        $viewerid = snapp_security::get_os_viewerid($requestvars);

        //we shouldn't ever get a problem here as verified requests should have info....
        if (!$container || !$ownerid) {
            return null;
        }

        return GadgetMapping::user_map_exists($ownerid, $containerid);
    }
}
