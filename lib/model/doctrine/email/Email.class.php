<?php

/**
 * Email
 * 
 * This class has been auto-generated by the Doctrine ORM Framework
 * 
 * @package    e-venement
 * @subpackage model
 * @author     Baptiste SIMON <baptiste.simon AT e-glop.net>
 * @version    SVN: $Id: Builder.php 7490 2010-03-29 19:53:27Z jwage $
 */
class Email extends PluginEmail
{
  protected $nospool = false;
  
  public function setUp()
  {
    parent::setUp();
    $this->_table->getTemplate('Doctrine_Template_Searchable')
      ->getPlugin()
      ->setOption('analyzer',new MySearchAnalyzer());
  }

  public function setNoSpool($boolean = true)
  {
    $this->nospool = $boolean;
  }
  
  public function __toString()
  {
    sfApplicationConfiguration::getActive()->loadHelpers(array('Date'));
    return format_date($this->updated_at).' '.substr($this->field_subject,0,20).'...';
  }
  public function save(Doctrine_Connection $conn = null)
  {
    if ( $this->sent )
      return $this;
    
    // send email
    if ( $this->not_a_test )
      $this->sent = $this->send();
    else
      $this->sendTest();
    
    $this->updated_at = date('Y-m-d H:i:s');
    if ( sfContext::hasInstance() )
    if ( sfContext::getInstance()->getUser() instanceof sfGuardSecurityUser )
    if ( sfContext::getInstance()->getUser()->getId() )
      $this->sf_guard_user_id = sfContext::getInstance()->getUser()->getId();
    
    return parent::save($conn);
  }
}
