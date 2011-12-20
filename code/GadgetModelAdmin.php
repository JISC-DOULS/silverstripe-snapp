<?php

class GadgetModelAdmin extends ModelAdmin {

  public static $managed_models = array(
      'GadgetContainer',
      'GadgetCertificate',
      'GadgetKeySecret'
   );

  static $url_segment = 'gadgets'; // will be linked as /admin/gadgets
  static $menu_title = 'Gadget data';

}
