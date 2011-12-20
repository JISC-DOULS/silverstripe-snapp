<?php
/*
 * Libs to help with verifying OAuth requests
 * Ported from Moodle SNAPP plugin
 * Connects to module dataobjects
 */

class snapp_security {
    //You MUST update these values in your site config
    public static $hash_secret = 'Az3r8&^t+=pPQZa';
    public static $salt = '#a[s]jkl:12!';

    /**
     * Uses Oauth lib to verify whether an opensocial signed request is genuine
     * Supports requests signed using:
     * RSA_SHA1
     * HMAC-SHA1
     * @param array $req request array (e.g. $_REQUEST)
     * returns OBJECT
     * ->success BOOLEAN
     * ->error STRING Any errors generated
     * ->debug STRING Debug information
     */
    public static function verify_opensocial_request($req = array('empty')) {
        //set param default (can't set to global in func declaration)
        if (isset($req[0]) && $req[0] == 'empty') {
            $req = $_REQUEST;
        }

        $response = new stdClass();
        $response->success = false;
        $response->error = '';
        $response->debug = 'Verifying request.';

        $sigtype = '';
        //Check $_REQUEST for a supported OAuth signature
        if (!empty($req['oauth_signature_method'])) {
            if (strtolower($req['oauth_signature_method']) == 'rsa-sha1') {
                $sigtype = 'RSA-SHA1';
            } else if (strtolower($req['oauth_signature_method']) == 'hmac-sha1') {
                $sigtype = 'HMAC-SHA1';
            }
        }

        //Build a request object from the current request
        $request = OAuthRequest::from_request(null, null, $req);

        //Initialize the new signature method
        switch ($sigtype) {
            case 'RSA-SHA1':
                $response->debug .= "\rRSA-SHA1 method detected.";
                try {
                    $signaturemethod = new snapp_sig_rsasha1();
                    //Check the request signature (db interaction is in cert class)
                    $signaturevalid = $signaturemethod->check_signature($request, null, null, $request->get_parameter('oauth_signature'));
                } catch (Exception $e) {
                    $response->error = $e->getMessage();
                    return $response;
                }
                break;
            case 'HMAC-SHA1':
                $response->debug .= "\rHMAC-SHA1 method detected.";
                $signaturemethod = new OAuthSignatureMethod_HMAC_SHA1();
                //Check that we have consumer key/secret the request signature
                try {
                    //get request consumer key
                    $consumerkey = $request->get_parameter('oauth_consumer_key');
                    //check if match in db and get consumer secret
                    $consumer = new stdClass();
                    if (!$consumer->secret = self::get_consumer_secret($consumerkey)) {
                        throw new Exception('Key not registered on this system.');
                    }
                    //check conatiner not disabled
                    if (!self::iscontainer_name_enabled($consumerkey) === false) {
                        throw new Exception('The system has been disabled.');//Container disabled so don't verify
                    }
                    //check signature
                    $token = new stdClass();
                    $token->secret = '';// no token secret

                    $signaturevalid = $signaturemethod->check_signature($request, $consumer, $token,
                        $request->get_parameter('oauth_signature'));
                } catch (Exception $e) {
                    $response->error = $e->errorcode;
                    return $response;
                }
                break;
            default:
                $response->debug .= "\rNo compatible Oauth signature method detected.";
                $response->error = "notsigned";
                return $response;
        }

        //Test success
        if ($signaturevalid == true) {
            $response->debug .= "\rValidation successfull.";
            $response->success = true;
        } else {
            $response->error = "Validation failed";
        }

        return $response;
    }

    /**
     * Returns the openscocial container name based on signed request param (RSA-SHA1)
     * @param $req (see get_val_from_request)
     * returns string of container name or empty
     */
    public static function get_os_container($req = array('empty')) {
        if (self::get_val_from_request('oauth_consumer_key', $req)) {
            return self::get_val_from_request('oauth_consumer_key', $req);
        } else if (self::get_val_from_request('opensocial_container', $req)) {
            return self::get_val_from_request('opensocial_container', $req);
        } else {
            return '';
        }
    }

    /**
     * Returns the openscocial container name based on opensocial_container
     * @param $req (see get_val_from_request)
     */
    public static function get_os_container_name($req = array('empty')) {
        if (self::get_val_from_request('opensocial_container', $req)) {
            return self::get_val_from_request('opensocial_container', $req);
        } else {
            return self::get_os_container($req);
        }
    }

    /**
     * Returns the openscocial public key name based on signed request param
     * @param $req (see get_val_from_request)
     * returns string of key name or empty
     */
    public static function get_os_pubkey($req = array('empty')) {
        if (self::get_val_from_request('xoauth_signature_publickey', $req)) {
            return self::get_val_from_request('xoauth_signature_publickey', $req);
        } else if (self::get_val_from_request('xoauth_publickey', $req)) {
            return self::get_val_from_request('xoauth_publickey', $req);
        } else {
            return '';
        }
    }

    /**
     * Get owner id from an opensocial signed request
     * @param unknown_type $req
     */
    public static function get_os_ownerid($req = array('empty')) {
        return self::get_val_from_request('opensocial_owner_id', $req);
    }
    /**
     * Get viewer id from an opensocial signed request
     * @param unknown_type $req
     */
    public static function get_os_viewerid($req = array('empty')) {
        return self::get_val_from_request('opensocial_viewer_id', $req);
    }

    /**
     * Returns the value from a request
     * @param $req can be array, oauth request obj or empty array ($_REQUEST will be used)
     * @param string $val
     * returns value or false if not found
     */
    private static function get_val_from_request($val, $req = array('empty')) {
        if (is_array($req)) {
            if (isset($req[0]) && $req[0] == 'empty') {
                $req = $_REQUEST;
            }
            if (isset($req[$val])) {
                return $req[$val];
            }
        } else if ($req instanceof OAuthRequest) {
            if ($req->get_parameter($val) !== null) {
                return $req->get_parameter($val);
            }
        }
        return false;
    }

    /**
     * Send a consumer key or container name and determine if container enabled
     * @param string $consumerkey
     * @return boolean
     */
    public static function iscontainer_name_enabled($consumerkey) {
        //First check container class for match
        $container = GadgetContainer::iscontainer_name_enabled($consumerkey);
        if (!is_null($container)) {
            return $container;
        }
        //Then check consumer key class for match (checking container of match)
        $container = GadgetKeySecret::iscontainer_name_enabled($consumerkey);
        if (!is_null($container)) {
            return $container;
        }
    }

    /**
     * Gets certificate contents from db
     * @param string $container The container name
     * @param string $keyname
     * returns string (or false if not in db)
     */
    public static function get_cert($container, $keyname) {
        $result = GadgetCertificate::get_cert($container, $keyname);
        if (!empty($result)) {
            return $result;
        } else {
            return false;
        }
    }

    /**
     * Inserts certificate record (along with associated container) into db table
     * You can still insert certs in disabled containers
     * @param string $container name of the container
     * @param string $keyname
     * @param string $cert
     * returns boolean success
     */
    public static function insert_cert($container, $keyname, $cert) {
        $container = Convert::raw2sql($container);
        $keyname = Convert::raw2sql($keyname);
        $cert = Convert::raw2sql($cert);
        $containerid = 0;
        if (!$result = DataObject::get_one('GadgetContainer', "Name = '$container'")) {
            $con = new GadgetContainer(array('Name' => $container));
            $containerid = $con->write();
        } else {
            $containerid = $result->ID;
        }

        //Check if already exists
        if (DataObject::get('GadgetCertificate', "KeyName = '$keyname' AND ContainerID = $containerid")) {
            return false;
        }

        $certrec = array();
        $certrec['keyname'] = $keyname;
        $certrec['containerid'] = $containerid;
        $certrec['cert'] = $cert;
        //Try and save to db
        $cert = new GadgetCertificate($certrec);
        return $cert->write();
    }


    /**
     * Returns the value of the consumer secret against consumer key
     * @param $key string
     * returns string
     */
    public static function get_consumer_secret($key) {
        $key = Convert::raw2sql($key);
        $result = DataObject::get_one('GadgetKeySecret', "ConsumerKey = '$key'");
        if ($result) {
            return $result->Secret;
        } else {
            return false;
        }
    }

    /**
     * Create hmac hash using secret key from snapp config
     * @param string $string
     * returns string
     */
    public static function hash($string) {

        return hash_hmac('sha256', $string, self::$hash_secret);
    }

    public static function create_mapuser_token($ownerid, $container) {
        return self::hash($ownerid . self::$salt . $container);
    }

    //Set for proxy + port eg blah.where.com:8080
    public static $proxy = '';

    public function get_from_web($url) {
        if (!$ch = curl_init($url)) {
            return false;
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);

        if (!ini_get('open_basedir') and !ini_get('safe_mode')) {
            // TODO: add version test for '7.10.5'
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        }

        if (!empty(self::$proxy)) {

            curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, false);

            curl_setopt($ch, CURLOPT_PROXY, self::$proxy);
        }


    }
}

/**
 * Extends OAuth RSA-SHA1 signature methods to handle getting certificates stored by snapp
 * public keys can be downloaded and added to db
 * @author j.platts@open.ac.uk
 *
 */
class snapp_sig_rsasha1 extends OAuthSignatureMethod_RSA_SHA1 {

    //Override so that certificate can be found in db or downloaded
    //Throws exceptions on error
    protected function fetch_public_cert(&$request) {
        //work out what cert we want
        $container = snapp_security::get_os_container($request);
        $keyname = snapp_security::get_os_pubkey($request);
        if ($container == '' || $keyname == '') {
            throw new Exception('Request not signed correctly.');//no cert details found...
        }

        //Check that the specified container is not disabled
        if (!snapp_security::iscontainer_name_enabled($container)) {
            throw new Exception('The system has been disabled.');//Container disabled so don't verify
        }

        //try and get from db
        $cert = snapp_security::get_cert($container, $keyname);
        if (!$cert) {
            //if not see if we can download cert (sometimes the provider will not give a valid url as you need to add to the db manually)
            //if successfull add to db
            $content = snapp_security::get_from_web($keyname);
            if (!$content) {
                throw new Exception('Unable to download certififcate.');//not valid url for cert
            }

            if (snapp_security::insert_cert($container, $keyname, $content)) {
                return $content;
            } else {
                throw new Exception('Error adding certificate.');//problem with cert e.g. not actual cert content
            }
        } else {
            return $cert;
        }

    }
    protected function fetch_private_cert(&$request) {
        //NOT SUPPORTED - FOR SIGNING REQUESTS
    }
}
