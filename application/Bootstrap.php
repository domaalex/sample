<?php

class Bootstrap extends Zend_Application_Bootstrap_Bootstrap {

  protected function _initDoctype() {
    $this->bootstrap('view');
    $view = $this->getResource('view');
    $view->doctype('XHTML1_STRICT');

    $view->headScript()->appendFile('/js/jquery-1.11.1.min.js');
  }

}