<?php
class GadgetCertificate extends DataObject {

    public static $db = array (
        'KeyName' => 'VarChar(255)',
        'Cert' => 'Text'
    );

    public static $has_one = array (
        'Container' => 'GadgetContainer',
    );

    public static $summary_fields = array (
        'ContainerName', 'KeyName'
    );

    public static $searchable_fields = array (
        'ContainerID', 'KeyName'
    );

    public Function getContainerName() {
        return $this->getComponent('Container')->Name;
    }

    /**
     * Add in default records - need to do this in function to get container value
     */
    public function requireDefaultRecords() {
        //Hack.. Run container defaults first
        $gc = new GadgetContainer();
        $gc->requireDefaultRecords();
        $retarray = array();
        //TODO get_one targeted query
        $containers = DataObject::get('GadgetContainer');
        $googleid = 0;
        foreach ($containers as $container) {
            if ($container->Name == 'www.google.com') {
                $googleid = $container->ID;
            }
        }
        if ($googleid) {
            $retarray = array(
            'KeyName' => 'pub.1210278512.2713152949996518384.cer',
            'Cert' => '-----BEGIN CERTIFICATE-----
MIIDBDCCAm2gAwIBAgIJAK8dGINfkSTHMA0GCSqGSIb3DQEBBQUAMGAxCzAJBgNV
BAYTAlVTMQswCQYDVQQIEwJDQTEWMBQGA1UEBxMNTW91bnRhaW4gVmlldzETMBEG
A1UEChMKR29vZ2xlIEluYzEXMBUGA1UEAxMOd3d3Lmdvb2dsZS5jb20wHhcNMDgx
MDA4MDEwODMyWhcNMDkxMDA4MDEwODMyWjBgMQswCQYDVQQGEwJVUzELMAkGA1UE
CBMCQ0ExFjAUBgNVBAcTDU1vdW50YWluIFZpZXcxEzARBgNVBAoTCkdvb2dsZSBJ
bmMxFzAVBgNVBAMTDnd3dy5nb29nbGUuY29tMIGfMA0GCSqGSIb3DQEBAQUAA4GN
ADCBiQKBgQDQUV7ukIfIixbokHONGMW9+ed0E9X4m99I8upPQp3iAtqIvWs7XCbA
bGqzQH1qX9Y00hrQ5RRQj8OI3tRiQs/KfzGWOdvLpIk5oXpdT58tg4FlYh5fbhIo
VoVn4GvtSjKmJFsoM8NRtEJHL1aWd++dXzkQjEsNcBXwQvfDb0YnbQIDAQABo4HF
MIHCMB0GA1UdDgQWBBSm/h1pNY91bNfW08ac9riYzs3cxzCBkgYDVR0jBIGKMIGH
gBSm/h1pNY91bNfW08ac9riYzs3cx6FkpGIwYDELMAkGA1UEBhMCVVMxCzAJBgNV
BAgTAkNBMRYwFAYDVQQHEw1Nb3VudGFpbiBWaWV3MRMwEQYDVQQKEwpHb29nbGUg
SW5jMRcwFQYDVQQDEw53d3cuZ29vZ2xlLmNvbYIJAK8dGINfkSTHMAwGA1UdEwQF
MAMBAf8wDQYJKoZIhvcNAQEFBQADgYEAYpHTr3vQNsHHHUm4MkYcDB20a5KvcFoX
gCcYtmdyd8rh/FKeZm2me7eQCXgBfJqQ4dvVLJ4LgIQiU3R5ZDe0WbW7rJ3M9ADQ
FyQoRJP8OIMYW3BoMi0Z4E730KSLRh6kfLq4rK6vw7lkH9oynaHHWZSJLDAp17cP
j+6znWkN9/g=
-----END CERTIFICATE-----'
            );
            $retarray['ContainerID'] = $googleid;
        }

        if (!empty($retarray)) {
            $this->set_stat('default_records', array($retarray));
        }
        return parent::requireDefaultRecords();
    }

    protected function onBeforeWrite() {
        //Check certificate is valid
        if (strpos($this->Cert, '-----BEGIN CERTIFICATE-----') !== 0) {
            //expected format of cert check failed
            user_error('Invalid certificate', E_USER_ERROR);
            exit();
        }
        return parent::onBeforeWrite();
    }

    /**
     * Gets a certificate that matches container name and cert keyname
     * @param string $container
     * @param string $keyname
     * @return string certificate or empty string
     */
    public static function get_cert(string $container, string $keyname) {
        $con = Convert::raw2sql($container);
        $key = Convert::raw2sql($keyname);
        $result = DataObject::get(
            'GadgetCertificate',
            "KeyName = '$key' AND con.Name = '$con'",
            "",
            "LEFT JOIN GadgetContainer AS con ON con.ID = ContainerID"
        );
        if ($result) {
            return $result->First()->Cert;
        } else {
            return '';
        }
    }
}
