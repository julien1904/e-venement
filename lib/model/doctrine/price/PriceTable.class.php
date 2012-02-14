<?php

/**
 * PriceTable
 * 
 * This class has been auto-generated by the Doctrine ORM Framework
 */
class PriceTable extends PluginPriceTable
{
    /**
     * Returns an instance of this class.
     *
     * @return object PriceTable
     */
    public static function getInstance()
    {
        return Doctrine_Core::getTable('Price');
    }
    
    public function fetchOneByName($name)
    {
      $q = $this->createQuery('p')->andWhere('name = ?',$name);
      return $q->fetchOne();
    }
}
