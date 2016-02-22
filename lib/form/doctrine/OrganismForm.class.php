<?php

/**
 * Organism form.
 *
 * @package    e-venement
 * @subpackage form
 * @author     Baptiste SIMON <baptiste.simon AT e-glop.net>
 * @version    SVN: $Id: sfDoctrineFormTemplate.php 23810 2009-11-12 11:07:44Z Kris.Wallsmith $
 */
class OrganismForm extends BaseOrganismForm
{
  /**
   * @see AddressableForm
   */
  public function configure()
  {
    // removes the emails_list widget to avoid loosing data...
    unset($this->widgetSchema['emails_list']);
    
    $this->widgetSchema   ['phone_number'] = new sfWidgetFormInputText();
    $this->validatorSchema['phone_number'] = new sfValidatorPass(array('required' => false));
    
    $this->widgetSchema   ['phone_type']   = new liWidgetFormDoctrineJQueryAutocompleterGuide(array(
      'model' => 'PhoneType',
      'url'   => url_for('phone_type/ajax'),
      'method_for_query' => 'findOneByName',
    ));
    $this->widgetSchema   ['phone_type']->getStylesheets();
    $this->widgetSchema   ['phone_type']->getJavascripts();
    $this->validatorSchema['phone_type'] = new sfValidatorPass(array(
      'required' => false,
    ));
    
    $this->widgetSchema['groups_list'] = new cxWidgetFormDoctrineJQuerySelectMany(array(
      'model' => 'Group',
      'url'   => cross_app_url_for('rp', 'group/ajax'),
      'config' => '{ max: 300 }',
    ));
    $this->widgetSchema['groups_list']->setIdFormat('groups_list[%s]');
    
    $this->validatorSchema['url'] = new liValidatorUrl(array(
      'required' => false,
    ));
    
    $this->widgetSchema['organism_category_id']->setOption('order_by',array('name',''));
    $this->widgetSchema['professional_id']
      ->setOption('query', Doctrine::getTable('Professional')->createQuery('p')->andWhere('o.id = ?', $this->object->id))
      ->setOption('order_by',array('c.name, c.firstname',''))
      ->setOption('expanded', true);
    
    // adding artificial mandatory fields
    if ( is_array($force = sfConfig::get('app_organism_force_fields', array())) )
    foreach ( $force as $field )
    {
      if ( isset($this->validatorSchema[$field]) )
        $this->validatorSchema[$field]->setOption('required', true);
    }

    parent::configure();
  }
  
  public function doSave($con = NULL)
  {
    // force uppercase
    if ( is_array($upper = sfConfig::get('app_organism_force_uppercase', array())) )
    foreach ( $upper as $field )
    if ( isset($this->values[$field]) )
      $this->values[$field] = strtoupper($this->values[$field]);
    
    // force uppercase first letter
    if ( is_array($upper = sfConfig::get('app_organism_force_ucfirst', array())) )
    foreach ( $upper as $field )
    if ( isset($this->values[$field]) )
      $this->values[$field] = ucfirst($this->values[$field]);
    
    return parent::doSave($con);
  }
  
  public function saveGroupsList($con = null)
  {
    $this->correctGroupsListWithCredentials();
    parent::saveGroupsList($con);
  }

  public function displayOnly($fieldname = NULL)
  {
    unset(
      $this->widgetSchema['emails_list'],
      $this->widgetSchema['groups_list'],
      $this->widgetSchema['events_list'],
      $this->widgetSchema['manifestations_list']
    );
    
    // BUG: 2013-04-12
    if ( is_null($fieldname) )
      return $this;
    
    if ( !($this->widgetSchema[$fieldname] instanceof sfWidgetForm) )
      throw new liEvenementException('Fieldname "'.$fieldname.'" not found.');
    
    foreach ( $this->widgetSchema->getFields() as $name => $widget )
    {
      if ( $name != $fieldname )
        $this->widgetSchema[$name] = new sfWidgetFormInputHidden();
    }
    
    return $this;
  }
}
