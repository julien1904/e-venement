<?php
/**********************************************************************************
*
*	    This file is part of e-venement.
*
*    e-venement is free software; you can redistribute it and/or modify
*    it under the terms of the GNU General Public License as published by
*    the Free Software Foundation; either version 2 of the License.
*
*    e-venement is distributed in the hope that it will be useful,
*    but WITHOUT ANY WARRANTY; without even the implied warranty of
*    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*    GNU General Public License for more details.
*
*    You should have received a copy of the GNU General Public License
*    along with e-venement; if not, write to the Free Software
*    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*
*    Copyright (c) 2006-2015 Baptiste SIMON <baptiste.simon AT e-glop.net>
*    Copyright (c) 2006-2015 Libre Informatique [http://www.libre-informatique.fr/]
*
***********************************************************************************/
?>
<?php
$this->getContext()->getConfiguration()->loadHelpers('I18N');
$survey = $this->form->getObject();
$this->lines = array('title' => array(), 'details' => array());
$queries = new Doctrine_Collection('SurveyQuery');

// main header
$this->lines['title']['name']         = (string)$survey;
$this->lines['title']['professional'] = '';
$this->lines['title']['organism']     = '';
$this->lines['title']['email']        = '';
$this->lines['title']['transaction']  = '';
$this->lines['title']['tickets']      = '';
foreach ( $this->survey->Queries as $query )
{
  foreach ( $query->Options as $option )
    $this->lines['title'][$query->slug.'-'.$option->id] = '';
  $this->lines['title'][$query->slug.($query->Options->count() > 0 ? '-'.$query->Options[0]->id : '')] = (string)$query;
  $queries[$query->id] = $query;
}

// second header
$this->lines['details']['name']         = __('Contact');
$this->lines['details']['professional'] = __('Function');
$this->lines['details']['organism']     = __('Organism');
$this->lines['details']['email']        = __('Email');
$this->lines['details']['transaction']  = __('Transaction');
$this->lines['details']['tickets']      = __('Tickets');
foreach ( $this->survey->Queries as $query )
{
  $this->lines['details'][$query->slug] = '';
  foreach ( $query->Options as $option )
    $this->lines['details'][$query->slug.'-'.$option->id] = $option->value;
}

// lines
$i = 0;
foreach ( $this->survey->AnswersGroups as $group )
if ( $group->Answers->count() > 0 )
{
  $this->lines[$i] = array();
  $this->lines[$i]['name']         = (string)$group->Contact;
  $this->lines[$i]['professional'] = (string)$group->Professional->name_type;
  $this->lines[$i]['organism']     = (string)$group->Professional->Organism;
  $this->lines[$i]['email']        = $group->professional_id && $group->Professional->email
    ? $group->Professional->email
    : $group->contact_id && $group->Contact->email
    ? $group->Contact->email
    : $group->Professional->Organism->email;
  $this->lines[$i]['transaction']  = '#'.$group->transaction_id;
  
  // counting valid tickets in the transaction
  $cpt = 0;
  foreach ( $group->Transaction->Tickets as $ticket )
  {
    if ( $ticket->hasBeenCancelled() || $ticket->duplicating )
      continue;
    if ( $group->Transaction->Order->count() > 0 || $ticket->printed_at || $ticket->integrated_at )
      $cpt++;
  }
  $this->lines[$i]['tickets'] = $cpt;
  
  $cid = null;
  foreach ( $group->Answers as $answer )
  {
    if ( $answer->contact_id )
    {
      if ( !is_null($cid) && $answer->contact_id != $cid )
      {
        $i++;
        
        foreach ( $group->Transaction->Tickets as $ticket )
        if ( $ticket->contact_id == $answer->contact_id )
        {
          if ( $ticket->hasBeenCancelled() || $ticket->duplicating )
            continue;
          if ( $group->Transaction->Order->count() > 0 || $ticket->printed_at || $ticket->integrated_at )
            $cpt++;
        }
        $this->lines[$i]['tickets'] = $cpt;
      }
      
      $this->lines[$i]['name']         = (string)$answer->Contact;
      $this->lines[$i]['professional'] = '';
      $this->lines[$i]['organism']     = (string)$group->Professional->Organism;
      $this->lines[$i]['email']        = $answer->Contact->email
        ? $answer->Contact->email
        : $group->Professional->Organism->email;
      $this->lines[$i]['transaction'] = '#'.$group->transaction_id;
      $cid = $answer->contact_id;
    }
    
    if ( $queries[$answer->survey_query_id]->Options->count() == 0 )
      $this->lines[$i][$queries[$answer->survey_query_id]->slug] = $answer->value;
    else
    {
      $this->lines[$i][$queries[$answer->survey_query_id]->slug] = '';
      foreach ( $queries[$answer->survey_query_id]->Options as $option ) // init
      if ( !isset($this->lines[$i][$queries[$answer->survey_query_id]->slug.'-'.$option->id]) )
        $this->lines[$i][$queries[$answer->survey_query_id]->slug.'-'.$option->id] = '';
      foreach ( $queries[$answer->survey_query_id]->Options as $option ) // real
      if ( $option->value == $answer->value )
        $this->lines[$i][$queries[$answer->survey_query_id]->slug.'-'.$option->id] = $answer->value;
    }
  }
  $i++;
}

// get personal parameters for extractions
$params = OptionCsvForm::getDBOptions();

// forge the options of the extraction
$this->options = array(
 'ms'        => in_array('microsoft',$params['option']),    // microsoft-compatible extraction
 'fields'    => array_keys($this->lines['title']),
 'class'     => 'Contact',
 'noheader'  => true,
);

$this->outstream = 'php://output';
$this->delimiter = $this->options['ms'] ? ';' : ',';
$this->enclosure = '"';
$this->charset = sfConfig::get('software_internals_charset');

sfConfig::set('sf_escaping_strategy', false);
