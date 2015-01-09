<?php

class Application_Model_Team {

  protected $_dbTable = array();

  public function setDbTable($dbTable) {
    
    if (is_string($dbTable)) {
      $dbTable = new $dbTable();
    }
    
    if (!$dbTable instanceof Zend_Db_Table_Abstract) {
      throw new Exception('Invalid table data gateway provided');
    }
    
    $name = get_class($dbTable);
    $this->_dbTable[$name] = $dbTable;

    return $this;
  }

  protected function _getDbTable($name = 'Application_Model_DbTable_Team') {
    if (!isset($this->_dbTable[$name])) {
      
      $this->setDbTable($name);
    }

    return $this->_dbTable[$name];
  }

  public function getDbTable() {

    return $this->_getDbTable('Application_Model_DbTable_Team');
  }

  public function find($val) {
    $result = $this->getDbTable()->find($val);
    if (0 == count($result)) {
      return;
    }

    return $row = $result->current();
  }

  public function fetchAll() {
    $resultSet = $this->getDbTable()->fetchAll();

    return $resultSet;
  }


}