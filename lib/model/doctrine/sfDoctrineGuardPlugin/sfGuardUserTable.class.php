<?php

/**
 * sfGuardUserTable
 * 
 * This class has been auto-generated by the Doctrine ORM Framework
 */
class sfGuardUserTable extends PluginsfGuardUserTable
{
  public function createQuery($alias = 'u')
  {
    $p  = 'p'  == $alias ? 'p1'  : 'p';
    $me = 'me' == $alias ? 'me1' : 'me';
    $e  = 'e'  == $alias ? 'e1'  : 'e';
    $ws = 'ws' == $alias ? 'ws1' : 'ws';
    
    $q = parent::createQuery($alias)
      ->leftJoin("$alias.MetaEvents $me")
      ->leftJoin("$alias.Workspaces $ws")
    ;
    
    if ( sfConfig::get('project_internals_users_domain', '') )
    {
      $q->leftJoin("$alias.Domain domain")
        ->andWhere('domain.name LIKE ?', '%.'.sfConfig::get('project_internals_users_domain', ''));
    }
    
    return $q;
  }
    /**
     * Returns an instance of this class.
     *
     * @return object sfGuardUserTable
     */
    public static function getInstance()
    {
        return Doctrine_Core::getTable('sfGuardUser');
    }
    
    public function fetchOneByUsername($username)
    {
      $q = $this->createQuery('u')->andWhere('username = ?',$username);
      return $q->fetchOne();
    }
    
    public function retrieveByUsername($username, $isActive = true)
    {
      return $this->createQuery('u')
        ->andWhere('u.username = ?', $username)
        ->fetchOne();
    }
}
