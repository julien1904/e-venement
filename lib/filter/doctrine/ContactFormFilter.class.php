<?php

/**
 * Contact filter form.
 *
 * @package    e-venement
 * @subpackage filter
 * @author     Baptiste SIMON <baptiste.simon AT e-glop.net>
 * @version    SVN: $Id: sfDoctrineFormFilterTemplate.php 23810 2009-11-12 11:07:44Z Kris.Wallsmith $
 */
class ContactFormFilter extends BaseContactFormFilter
{
  protected $noTimestampableUnset = true;

  /**
   * @see AddressableFormFilter
   */
  public function configure()
  {
    sfContext::getInstance()->getConfiguration()->loadHelpers(array('I18N'));
    $this->widgetSchema['groups_list']->setOption(
      'order_by',
      array('u.id IS NULL DESC, u.username, name','')
    );
    
    $this->widgetSchema['emails_list']->setOption('query',Doctrine::getTable('Email')
      ->createQuery()
      ->andWhere('sent')
    );
    
    // has postal address ?
    $this->widgetSchema   ['has_address'] = $this->widgetSchema   ['npai'];
    $this->validatorSchema['has_address'] = $this->validatorSchema['npai'];
    
    // has email address ?
    $this->widgetSchema   ['has_email'] = $this->widgetSchema   ['npai'];
    $this->validatorSchema['has_email'] = $this->validatorSchema['npai'];
    
    // organism
    $this->widgetSchema   ['organism_id'] = new sfWidgetFormDoctrineJQueryAutocompleter(array(
      'model' => 'Organism',
      'url'   => url_for('organism/ajax'),
    ));
    $this->validatorSchema['organism_id'] = new sfValidatorInteger(array('required' => false));
    
    // organism category
    $this->widgetSchema   ['organism_category_id'] = new sfWidgetFormDoctrineChoice(array(
      'model'     => 'OrganismCategory',
      'add_empty' => true,
      'order_by'  => array('name',''),
    ));
    $this->validatorSchema['organism_category_id'] = new sfValidatorInteger(array('required' => false));
    
    // professional type
    $this->widgetSchema   ['professional_type_id'] = new sfWidgetFormDoctrineChoice(array(
      'model'     => 'ProfessionalType',
      'add_empty' => true,
      'order_by'  => array('name',''),
    ));
    $this->validatorSchema['professional_type_id'] = new sfValidatorInteger(array('required' => false));
    
    $this->widgetSchema   ['not_groups_list'] = $this->widgetSchema   ['groups_list'];
    $this->validatorSchema['not_groups_list'] = $this->validatorSchema['groups_list'];
    
    $years = sfContext::getInstance()->getConfiguration()->yob;
    $this->widgetSchema   ['YOB'] = new sfWidgetFormFilterDate(array(
      'from_date'=> new sfWidgetFormDate(array(
        'format' => '%year% %month% %day%',
        'years'  => $years,
      )),
      'to_date'   => new sfWidgetFormDate(array(
        'format' => '%year% %month% %day%',
        'years'  => $years,
      )),
      'with_empty'=> false,
      'template'  => '<span class="from_year">'.__('From %from_date%').'</span> <span class="to_year">'.__('to %to_date%').'</span>',
    ));
    $this->validatorSchema['YOB'] = new sfValidatorDateRange(array(
      'from_date' => new sfValidatorDate(array('required' => false,)),
      'to_date'   => new sfValidatorDate(array('required' => false,)),
    ));
    
    // events
    $this->widgetSchema   ['events_list'] = new sfWidgetFormDoctrineChoice(array(
      'model' => 'Event',
      'order_by' => array('name','asc'),
      'multiple' => true,
    ));
    $this->validatorSchema['events_list'] = new sfValidatorDoctrineChoice(array(
      'required' => false,
      'model'    => 'Event',
      'multiple' => true,
    ));
    $this->widgetSchema   ['event_categories_list'] = new sfWidgetFormDoctrineChoice(array(
      'model' => 'EventCategory',
      'order_by' => array('name','asc'),
      'multiple' => true,
    ));
    $this->validatorSchema['event_categories_list'] = new sfValidatorDoctrineChoice(array(
      'required' => false,
      'model'    => 'EventCategory',
      'multiple' => true,
    ));
    $this->widgetSchema   ['meta_events_list'] = new sfWidgetFormDoctrineChoice(array(
      'model' => 'MetaEvent',
      'order_by' => array('name','asc'),
      'multiple' => true,
    ));
    $this->validatorSchema['meta_events_list'] = new sfValidatorDoctrineChoice(array(
      'required' => false,
      'model'    => 'MetaEvent',
      'multiple' => true,
    ));
    $this->widgetSchema   ['prices_list'] = new sfWidgetFormDoctrineChoice(array(
      'model' => 'Price',
      'order_by' => array('name, description',''),
      'multiple' => true,
    ));
    $this->validatorSchema['prices_list'] = new sfValidatorDoctrineChoice(array(
      'required' => false,
      'model'    => 'Price',
      'multiple' => true,
    ));
    
    parent::configure();
  }
  
  public function getFields()
  {
    $fields = parent::getFields();
    $fields['postalcode']           = 'Postalcode';
    $fields['YOB']                  = 'YOB';
    $fields['organism_id']          = 'OrganismId';
    $fields['organism_category_id'] = 'OrganismCategoryId';
    $fields['professional_type_id'] = 'OrganismCategoryId';
    $fields['has_email']            = 'HasEmail';
    $fields['has_address']          = 'HasAddress';
    $fields['groups_list']          = 'GroupsList';
    $fields['not_groups_list']      = 'NotGroupsList';
    $fields['emails_list']          = 'EmailsList';
    $fields['events_list']          = 'EventsList';
    $fields['event_categories_list']= 'EventCategoriesList';
    $fields['meta_events_list']     = 'MetaEventsList';
    $fields['prices_list']          = 'PricesList';
    
    return $fields;
  }
  
  public function addEmailsListColumnQuery(Doctrine_Query $q, $field, $value)
  {
    $a = $q->getRootAlias();
    
    if ( is_array($value) )
      $q->leftJoin("$a.Emails ce")
        ->leftJoin("p.Emails pe")
        ->andWhere('(TRUE')
        ->andWhere('ce.sent = TRUE')
        ->andWhereIn('ce.id',$value)
        ->orWhereIn('pe.id',$value)
        ->andWhere('pe.sent = TRUE')
        ->andWhere('TRUE)');
    
    return $q;
  }
  
  // links to the ticketting system module
  public function addEventsListColumnQuery(Doctrine_Query $q, $field, $value)
  {
    $a = $q->getRootAlias();
    
    if ( is_array($value) )
    {
      if ( !$q->contains("LEFT JOIN $a.Transactions transac") )
      $q->leftJoin("$a.Transactions transac");
      
      if ( !$q->contains("LEFT JOIN transac.Tickets tck") )
      $q->leftJoin('transac.Tickets tck');
      
      if ( !$q->contains("LEFT JOIN tck.Manifestation m") )
      $q->leftJoin('tck.Manifestation m');
      
      $q->andWhereIn('m.event_id',$value);
    }
    
    return $q;
  }
  public function addEventCategoriesListColumnQuery(Doctrine_Query $q, $field, $value)
  {
    $a = $q->getRootAlias();
    
    if ( is_array($value) )
    {
      if ( !$q->contains("LEFT JOIN $a.Transactions transac") )
      $q->leftJoin("$a.Transactions transac");
      
      if ( !$q->contains("LEFT JOIN transac.Tickets tck") )
      $q->leftJoin('transac.Tickets tck');
      
      if ( !$q->contains("LEFT JOIN tck.Manifestation m") )
      $q->leftJoin('tck.Manifestation m');
      
      if ( !$q->contains("LEFT JOIN m.Event event") )
      $q->leftJoin('m.Event event');
      
      $q->andWhereIn('event.event_category_id',$value);
    }
    
    return $q;
  }
  public function addMetaEventsListColumnQuery(Doctrine_Query $q, $field, $value)
  {
    $a = $q->getRootAlias();
    
    if ( is_array($value) )
    {
      if ( !$q->contains("LEFT JOIN $a.Transactions transac") )
      $q->leftJoin("$a.Transactions transac");
      
      if ( !$q->contains("LEFT JOIN transac.Tickets tck") )
      $q->leftJoin('transac.Tickets tck');
      
      if ( !$q->contains("LEFT JOIN tck.Manifestation m") )
      $q->leftJoin('tck.Manifestation m');
      
      if ( !$q->contains("LEFT JOIN m.Event event") )
      $q->leftJoin('m.Event event');
      
      if ( !$q->contains("LEFT JOIN event.MetaEvent mev") )
      $q->leftJoin('event.MetaEvent mev');
      
      $q->andWhereIn('mev.id',$value);
    }
    
    return $q;
  }

  public function addPricesListColumnQuery(Doctrine_Query $q, $field, $value)
  {
    $a = $q->getRootAlias();
    
    if ( is_array($value) )
    {
      if ( !$q->contains("LEFT JOIN $a.Transactions transac") )
      $q->leftJoin("$a.Transactions transac");
      
      if ( !$q->contains("LEFT JOIN transac.Tickets tck") )
      $q->leftJoin('transac.Tickets tck');
      
      if ( !$q->contains("LEFT JOIN tck.Price price") )
      $q->leftJoin('tck.Price price');
      
      $q->andWhereIn('price.id',$value);
    }
    
    return $q;
  }

  public function addGroupsListColumnQuery(Doctrine_Query $q, $field, $value)
  {
    $a = $q->getRootAlias();
    
    if ( is_array($value) )
    {
      if ( !$q->contains("LEFT JOIN $a.Groups gc") )
        $q->leftJoin("$a.Groups gc");
      
      if ( !$q->contains("LEFT JOIN p.Groups gp") )
        $q->leftJoin("p.Groups gp");
      
      $q->andWhere('(TRUE')
        ->andWhereIn("gc.id",$value)
        ->orWhereIn("gp.id",$value)
        ->andWhere('TRUE)');
    }
    
    return $q;
  }
  public function addNotGroupsListColumnQuery(Doctrine_Query $q, $field, $value)
  {
    $a = $q->getRootAlias();
    
    if ( is_array($value) )
    {
      /*
      if ( !$q->contains("LEFT JOIN $a.Groups gc") )
        $q->leftJoin("$a.Groups gc");
      
      if ( !$q->contains("LEFT JOIN p.Groups gp") )
        $q->leftJoin("p.Groups gp");
      */
      
      $q1 = new Doctrine_Query();
      $q1->select('tmp1.contact_id')
        ->from('GroupContact tmp1')
        ->andWhereIn('tmp1.group_id',$value);
      $q2 = new Doctrine_Query();
      $q2->select('tmp2.professional_id')
        ->from('GroupProfessional tmp2')
        ->andWhereIn('tmp2.group_id',$value);
      
      $q->andWhere("$a.id NOT IN (".$q1.")",$value) // hack for inserting $value
        ->andWhere("p.id NOT IN (".$q2.")",$value); // hack for inserting $value
    }
    
    return $q;
  }
  public function addHasAddressColumnQuery(Doctrine_Query $q, $field, $value)
  {
    if ( $value === '' )
      return $q;
    
    $a = $q->getRootAlias();
    if ( $value )
      return $q->addWhere("$a.postalcode IS NOT NULL AND $a.postalcode != '' AND $a.city IS NOT NULL AND $a.postalcode != ''");
    else
      return $q->addWhere("$a.postalcode IS     NULL OR $a.postalcode = '' OR $a.city IS     NULL OR $a.city = ''");
  }
  public function addHasEmailColumnQuery(Doctrine_Query $q, $field, $value)
  {
    if ( $value === '' )
      return $q;
    
    $a = $q->getRootAlias();
    if ( $value )
      return $q->addWhere("$a.email IS NOT NULL");
    else
      return $q->addWhere("$a.email IS     NULL");
  }
  public function addProfessionalTypeIdColumnQuery(Doctrine_Query $q, $field, $value)
  {
    $a = $q->getRootAlias();
    if ( $value )
      $q->addWhere("pt.professional_type_id = ?",$value);
    return $q;
  }
  public function addOrganismIdColumnQuery(Doctrine_Query $q, $field, $value)
  {
    $a = $q->getRootAlias();
    if ( $value )
      $q->addWhere("o.id = ?",$value);
    return $q;
  }
  public function addOrganismCategoryIdColumnQuery(Doctrine_Query $q, $field, $value)
  {
    $a = $q->getRootAlias();
    if ( $value )
      $q->addWhere("o.organism_category_id = ?",$value);
    return $q;
  }
  public function addYOBColumnQuery(Doctrine_Query $q, $field, $value)
  {
    if ( $value['from'] )
      $q->addWhere('y.year >= ?',date('Y',strtotime($value['from'])));
    if ( $value['to'] )
      $q->addWhere('y.year <= ?',date('Y',strtotime($value['to'])));
    
    return $q;
  }
  public function addPostalcodeColumnQuery(Doctrine_Query $q, $field, $value)
  {
    $c = $q->getRootAlias();
    if ( intval($value['text']) > 0 )
      $q->addWhere("$c.postalcode LIKE ? OR (o.id IS NOT NULL AND o.postalcode LIKE ?)",array(intval($value['text']).'%',intval($value['text']).'%'));
    
    return $q;
  }
}
