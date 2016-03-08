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
*    Copyright (c) 2006-2016 Baptiste SIMON <baptiste.simon AT e-glop.net>
*    Copyright (c) 2006-2016 Libre Informatique [http://www.libre-informatique.fr/]
*
***********************************************************************************/
?>
<?php
  $vars = array(
    'options',
    'delimiter',
    'enclosure',
    'outstream',
    'charset',
    'lines',
  );
  foreach ( $vars as $key => $value )
  {
    unset($vars[$key]);
    $vars[$value] = $sf_data->getRaw($value);
  }
  $vars['options']['header'] = array(
    'meta_event'    => __('Meta event'),
    'event'         => __('Event'),
    'manifestation' => __('Date & time'),
    'workspace'     => __('Space'),
    'location'      => __('Location'),
    'contact'       => __('Contact'),
    'email'         => __('Email'),
    'zip'           => __('Postalcode'),
    'city'          => __('City'),
    'country'       => __('Country'),
    'price'         => __('Price'),
    'value'         => __('Value'),
    'taxes'         => __('Booking fees'),
  );
  
  //if ( sfConfig::get('app_ticketting_hide_demands') )
  //  unset($vars['options']['header']['asked']);
  
  include_partial('global/csv',$vars);
