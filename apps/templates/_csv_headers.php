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
*    Copyright (c) 2006-2011 Baptiste SIMON <baptiste.simon AT e-glop.net>
*    Copyright (c) 2006-2011 Libre Informatique [http://www.libre-informatique.fr/]
*
***********************************************************************************/
?>
<?php
  $fields = array(
    'title'     => __('Title'),
    'name'		  => __('Name'),
    'firstname' => __('Firstname'),
    'address'   => __('Address'),
    'postalcode'=> __('Postalcode'),
    'city'  		=> __('City'),
    'country'   => __('Country'),
    'npai'  		=> __('Npai'),
    'email'     					=> __('email'),
    'description' 				=> __('Keywords'),
    'phonename'  					=> __('Phonetype'),
    'phonenumber' 				=> __('Phonenumber'),
    '__YOBs__year'        => __('Birthdays'),
    '__Groups__name'      => __('Groups'),
    'events'              => __('Events'),
    'organism_category' 	=> __('Category of organism'),
    'organism_name'   		=> __('Organism'),
    'professional_department' => __('Department'),
    'professional_number' => __('Professional phone'),
    'professional_email'  => __('Professional email'),
    'professional_type_name' => __('Type of function'),
    'professional_name'   => __('Function'),
    'professional_description'=> __('Professional memo'),
    '__Professionals__events' => __('Events'),
    'organism_address'    => __('Address'),
    'organism_postalcode' => __('Postalcode'),
    'organism_city'   		=> __('City'),
    'organism_country'    => __('Country'),
    'organism_email'  		=> __('Email'),
    'organism_url'    		=> __('URL'),
    'organism_npai'   		=> __('Npai'),
    'organism_description'=> __('Description'),
    'organism_phonename'  => __('Phonetype'),
    'organism_phonenumber'=> __('Phonenumber'),
    '__Professionals__Groups__name'  => __('Professional groups'),
    '__Professionals__Organism__Groups__name'  => __("Organism's groups"),
    'information'         => __('Informations'),
  );
  
  // for organisms' close contact
  $fields['professional_important'] = __('Close contact');
  $fields['cc_title']     = __('Title');
  $fields['cc_name']      = __('Name');
  $fields['cc_firstname'] = __('Firstname');
  $fields['cc_type']      = __('Type');
  $fields['cc_function']    = __('Function');
  $fields['cc_phonenumber'] = __('Phonenumber');
  $fields['cc_email']     = __('Email');
  
  $line = array();
  if ( !$options['noheader'] )
  {
    if ( !isset($options['header']) )
    {
      foreach ( $options['fields'] as $fieldName )
 	      $line[] = isset($fields[$fieldName]) ? $fields[$fieldName] : $fieldName;
    }
    else
    {
      foreach ( $options['fields'] as $fieldname )
      {
        $line[$fieldname] = $options['header'][$fieldname];
  	  }
    }
    
    if ( $options['ms'] )
    foreach ( $line as $key => $value )
      $line[$key] = iconv($charset['db'], $charset['ms'], $value);
    
    fputcsv($outstream, $line, $delimiter, $enclosure);
    ob_flush();
  }
