<?php

/**
 * GroupTable
 * 
 * This class has been auto-generated by the Doctrine ORM Framework
 */
class GroupTable extends PluginGroupTable
{
  public function createQuery($alias = 'g')
  {
    //$alias = 'g'; // can't be something else, because of the "CASE WHEN" huge optimization & doctrine limitations
    $u  = 'u'  != $alias ? 'u'  : 'u1';
    $c  = 'c'  != $alias ? 'c'  : 'c1';
    $p  = 'p'  != $alias ? 'p'  : 'p1';
    $pc = 'pc' != $alias ? 'pc' : 'pc1';
    $pt = 'pt' != $alias ? 'pt' : 'pt1';
    $o  = 'o'  != $alias ? 'o'  : 'o1';
    
    $query = parent::createQuery($alias)
      ->leftJoin("$alias.User $u")
      //->leftJoin("$alias.Picture $p")
      ;
/*
      ->leftJoin("$alias.Professionals $p")
      ->leftJoin("$p.Contact $pc")
      ->leftJoin("$p.ProfessionalType $pt")
      ->leftJoin("$p.Organism $o")
      ->leftJoin("$alias.Contacts $c");
*/
    if ( !sfContext::hasInstance() )
      return $query;
    
    $sf_user = sfContext::getInstance()->getUser();
    // especially usefull for app "ws"
    if ( $sf_user->getId() === false )
      return $query;
    
    // NOW the rest is considered as an authenticated environment
    
    return $query
      ->leftJoin("$alias.Users users")
      ->andWhere("(CASE WHEN g.sf_guard_user_id IS NULL THEN CASE WHEN true = ? THEN true ELSE CASE WHEN true = ? THEN (SELECT count(sf_guard_user_id) > 0 FROM group_user WHERE group_id = g.id AND sf_guard_user_id = ?) ELSE false END END ELSE g.sf_guard_user_id = ? END)",array(
        $sf_user->hasCredential(array('admin-users','admin-power'),false),
        $sf_user->hasCredential('pr-group-common') ? true : false,
        $sf_user->getId(),
        $sf_user->getId(),
      ));
  }

  public function retrieveList()
  {
    return $this->createQuery('g');
  }
  
    /**
     * Returns an instance of this class.
     *
     * @return object GroupTable
     */
    public static function getInstance()
    {
        return Doctrine_Core::getTable('Group');
    }
}
