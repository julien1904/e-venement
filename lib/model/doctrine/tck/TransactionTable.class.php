<?php

/**
 * TransactionTable
 * 
 * This class has been auto-generated by the Doctrine ORM Framework
 */
class TransactionTable extends PluginTransactionTable
{
    /**
     * Returns an instance of this class.
     *
     * @return object TransactionTable
     */
    public static function getInstance()
    {
        return Doctrine_Core::getTable('Transaction');
    }
  
  public function createQuery($alias = 't')
  {
    $tck = 'tck' != $alias ? 'tck' : 'tck2';
    $m   = 'm'   != $alias ? 'm'   : 'm2';
    
    $q = parent::createQuery($alias);
    $a = $q->getRootAlias();
    $q->leftJoin("$a.Tickets $tck")
      ->leftJoin("$tck.Duplicatas duplicatas")
      ->leftJoin("$tck.Cancelled cancelled")
      ->leftJoin("$tck.Manifestation $m");
    return $q;
  }
  
  public function createQueryForLineal($a = 't')
  {
    $q = parent::createQuery($a);
    $q->leftJoin("$a.Tickets tck ON tck.transaction_id = t.id AND tck.duplicating IS NULL AND (tck.printed_at IS NOT NULL OR tck.cancelling IS NOT NULL OR tck.integrated_at IS NOT NULL)")
      ->leftJoin("$a.Invoice i")
      ->leftJoin('tck.Manifestation m')
      ->leftJoin('m.Event e')
      ->orderBy("$a.updated_at, $a.id, tck.updated_at");
    return $q;
  }
  
  public function fetchOneById($id)
  {
    $q = $this->createQuery()
      ->andWhere('id = ?',$id);
    return $q->fetchOne();
  }
  public function findOneById($id)
  {
    return $this->fetchOneById($id);
  }
  
  public function retrieveDebtsList()
  {
    $q = Doctrine_Query::create()->from('Transaction t');
    $this->addDebtsListBaseSelect($q);
    $q->addSelect('(SELECT (CASE WHEN COUNT(tck.id) = 0 THEN 0 ELSE SUM(tck.value) END) FROM Ticket tck WHERE '.$this->getDebtsListTicketsCondition().') AS outcomes')
      ->addSelect('(SELECT (CASE WHEN COUNT(pp.id)  = 0 THEN 0 ELSE SUM(pp.value)  END) FROM Payment pp WHERE pp.transaction_id = t.id) AS incomes')
      ->leftJoin('t.Contact c')
      ->leftJoin('t.Professional p')
      ->leftJoin('p.ProfessionalType pt')
      ->leftJoin('p.Organism o')
      ->andWhere('((SELECT (CASE WHEN COUNT(tck2.id) = 0 THEN 0 ELSE SUM(value) END) FROM Ticket tck2 WHERE '.$this->getDebtsListTicketsCondition('tck2').') - (SELECT (CASE WHEN count(p2.id) = 0 THEN 0 ELSE SUM(p2.value) END) FROM Payment p2 WHERE p2.transaction_id = t.id)) != 0');
    return $q;
  }
  public static function getDebtsListTicketsCondition($ticket_table = 'tck', $date = NULL)
  {
    $r = $ticket_table.'.transaction_id = t.id AND '.$ticket_table.'.duplicating IS NULL AND ('.$ticket_table.'.printed_at IS NOT NULL OR '.$ticket_table.'.integrated_at IS NOT NULL OR '.$ticket_table.'.cancelling IS NOT NULL)';
    if ( !is_null($date) )
      $r .= " AND ($ticket_table.cancelling IS NULL AND ($ticket_table.printed_at IS NOT NULL AND $ticket_table.printed_at < '$date' OR $ticket_table.integrated_at IS NOT NULL AND $ticket_table.integrated_at < '$date') OR $ticket_table.cancelling IS NOT NULL AND $ticket_table.created_at < '$date')";
    return $r;
  }
  public static function addDebtsListBaseSelect(Doctrine_Query $q)
  {
    return $q
      ->select($fields = 't.id, t.closed, t.updated_at, c.id, c.name, c.firstname, p.id, p.name, pt.id, pt.name, o.id, o.name, o.city')
      ->addSelect("'yummy' AS yummy") // a trick to avoid an obvious bug which removes the name of the field following directly the first ones (??)
      ;
  }
}
