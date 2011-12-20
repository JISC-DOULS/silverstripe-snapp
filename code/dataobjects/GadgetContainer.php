<?php
class GadgetContainer extends DataObject {

    public static $db = array (
        'Name' => 'VarChar(255)',
        'Enabled' => 'Boolean'
    );

    public static $defaults = array(
        'Enabled' => true
    );

    public static $indexes = array (
        'uniquecontainername' => array ( 'type' => 'unique', 'value' => 'Name' )
    );

    public static $has_many = array (
        'Certs' => 'GadgetCertificate',
        'Keys' => 'GadgetKey',
        'Users' => 'GadgetMapping'
    );

    public static $default_records = array (
        array (
            'Name' => 'www.google.com',
        )
    );

    public static $summary_fields = array (
        'Name', 'Enabled'
    );

    /**
     * Checks if container of given name is enabled
     * @param $name
     * @return null | boolean (true/false = enabled value)
     */
    public static function iscontainer_name_enabled($name) {
        if ($container = self::get_container_byname($name)) {
            return $container->Enabled;
        } else {
            return null;
        }
    }

    public static function get_containerid_fromname($name) {
        if ($container = self::get_container_byname($name)) {
            return $container->ID;
        } else {
            return null;
        }
    }

    public static function get_container_byname($name) {
        $name = Convert::raw2sql($name);
        return self::get_one('GadgetContainer', "Name = '$name'");
    }
}
