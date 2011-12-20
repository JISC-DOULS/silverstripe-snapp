<?php
class GadgetMapping extends DataObject {

    public static $db = array (
        'OwnerId' => 'VarChar(255)',
    );

    public static $has_one = array (
        'Container' => 'GadgetContainer',
        'User' => 'Member',
    );

    public static $summary_fields = array (
        'ContainerID', 'ContainerName', 'OwnerId'
    );

    public static $searchable_fields = array (
        'ContainerID', 'OwnerId', 'UserID'
    );

    public static $indexes = array (
        'uniquemapping' => array ( 'type' => 'unique', 'value' => 'OwnerId,ContainerID' )
    );

    public Function getContainerName() {
        return $this->getComponent('Container')->Name;
    }

    /**
     * Returns if a valid mapping record exists
     * Will return the id of Member it belongs to or null if none
     * @param int $ownerid
     * @param int $containerid
     * @return int | null
     */
    public static function user_map_exists(string $ownerid, int $containerid) {
        $ownerid = Convert::raw2sql($ownerid);
        //Check if owner in db table
        $gmember = DataObject::get_one('GadgetMapping', "OwnerId = $ownerid AND ContainerID = $containerid");
        if ($gmember) {
            return $gmember->UserID;
        } else {
            return null;
        }
    }

    public static function create_mapping(int $ownerid, string $containername, int $memberid) {
        //Check container details
        $con = GadgetContainer::get_container_byname($containername);
        if (!$con || $con->Enabled === false) {
            return false;
        }
        //Check if record exists
        $uid = self::user_map_exists($ownerid, $con->ID);
        if (!is_null($uid)) {
            //If record belongs to someone else there's a problem, else return OK
            if ($uid == $memberid) {
                return true;
            } else {
                return false;
            }
        }

        //Make record
        $newmap = new GadgetMapping(array(
            'OwnerId' => $ownerid,
            'ContainerID' => $con->ID,
            'UserID' => $memberid
        ));
        $newmap->write();
        return true;
    }
}
