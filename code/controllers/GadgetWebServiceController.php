<?php

/**
 * Controller designed to wrap around calls to defined services
 *
 * To call a service, use jsonservice/servicename/methodname
 *
 * @author marcus@silverstripe.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */

class GadgetWebServiceController extends WebServiceController {

    //This static will be set with Member id of gadget user (if mapped)
    public static $gmemberid = null;
    //This static will contain request vars in array
    public static $requestvars = array();

    /**
     * Override request handling and use OAuth to check request is signed
     * Will try and work out if request user is mapped to a member in the system
     * @param $request
     */
    public function handleRequest(SS_HTTPRequest $request) {
        try {
            //Don't bother with oauth if user is signed in & using xdomain ajax request
            if ((Director::isDev() || Director::isTest()) && Member::currentUserID()) {
                self::$gmemberid = Member::currentUserID();
            } else {
                self::$requestvars = $request->getVars();
                //TODO Authenticate OAuth request
                unset(self::$requestvars['url']);//unset extra param SS adds
                $verify = snapp_security::verify_opensocial_request(self::$requestvars);
                if ($verify->success != true) {
                    throw new Exception(_t('GADGET.WEBnotvalid',
                        'The request sent is not valid. ') . '[' . $verify->error . ']');
                }

                $conname = snapp_security::get_os_container(self::$requestvars);
                if (GadgetContainer::iscontainer_name_enabled($conname) != true) {
                    throw new Exception(_t('GADGET.WEBcontdis',
                        'This system has been disable by the administrator.'));
                }
                //TODO - Work out way of proper authentication/session so Member can be used
                self::$gmemberid = GadgetWebService::getUserMapping(self::$requestvars);
            }
            $response = Controller::handleRequest($request);
            if ($response instanceof SS_HTTPResponse) {
                $response->addHeader('Content-Type', 'application/'.$this->format);
            }
            return $response;
        } catch (WebServiceException $exception) {
            $this->response = new SS_HTTPResponse();
            $this->response->setStatusCode($exception->status);
            $this->response->setBody($this->ajaxResponse($exception->getMessage(), $exception->status));
        } catch (SS_HTTPResponse_Exception $e) {
            $this->response = $e->getResponse();
            $this->response->setBody($this->ajaxResponse($e->getMessage(), $e->getCode()));
        } catch (Exception $exception) {
            $this->response = new SS_HTTPResponse();
            $this->response->setStatusCode(500);
            $this->response->setBody($this->ajaxResponse($exception->getMessage(), 500));
        }

        return $this->response;
    }

    public static function getGadgetMemberID() {
        return self::$gmemberid;
    }
    public static function getRequestVars() {
        return self::$requestvars;
    }

    //Override index to support json-p
    public function index() {
        $return = parent::index();
        $callback = $this->request->getVar('callback');
        if ($callback && (Director::isDev() || Director::isTest()) && Member::currentUserID()) {
            return $callback . '(' . $return . ')';
        }
        return $return;
    }

}
