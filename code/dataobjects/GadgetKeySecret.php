<?php
class GadgetKeySecret extends DataObject {

    public static $db = array (
        'ConsumerKey' => 'VarChar(255)',
        'Secret' => 'VarChar(255)',
        'Info' => 'VarChar(255)'
    );

    public static $has_one = array (
        'Container' => 'GadgetContainer',
    );

    public static $indexes = array (
        'uniquekey' => array ( 'type' => 'unique', 'value' => 'ConsumerKey' )
    );

    public static $summary_fields = array (
        'ContainerName', 'ConsumerKey', 'Secret', 'Info'
    );

    public static $searchable_fields = array (
        'ContainerID', 'ConsumerKey', 'Info'
    );

    public Function getContainerName() {
        return $this->getComponent('Container')->Name;
    }

    /**
     * Checks if container owning Key of given name is enabled
     * @param $name
     * @return null | boolean (true/false = enabled value)
     */
    public static function iscontainer_name_enabled($name) {
        $aname = Convert::raw2sql($name);
        $keyrec = DataObject::get_one('GadgetKeySecret', "ConsumerKey = '$aname'");
        if ($keyrec) {
            return $keyrec->getComponent('Container')->Enabled;
        } else {
            return null;
        }
    }
}
