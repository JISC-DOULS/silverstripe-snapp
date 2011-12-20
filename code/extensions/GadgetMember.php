<?php
class GadgetMember extends DataObjectDecorator {
        public function extraStatics() {
            return array (
                'has_many' => array (
                    'GadgetMappings' => 'GadgetMapping'
                ),
            );
        }
}
