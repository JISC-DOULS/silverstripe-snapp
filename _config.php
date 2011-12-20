<?php

Director::addRules(50, array(
    'snappjsonservice/$Service/$Method' => 'GadgetWebServiceController',
    'snappxmlservice/$Service/$Method' => 'GadgetWebServiceController',
));

DataObject::add_extension('Member', 'GadgetMember');

GadgetAuthorisation::set_url('gadgetauth');
