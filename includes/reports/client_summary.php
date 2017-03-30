<?php
/**
 * Fakturo Reports Class.
 *
 * @package Fakturo
 * @subpackage Report
 *
 */


// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * client_summmary class.
 *
 * @since 0.6
 */
class client_summmary {
	/**
	 * Add hooks for reports.
	 */
	public static function hooks() {
		add_action('report_page_before_content_client_summary', array(__CLASS__, 'before_content'), 10, 2);
		add_action('report_page_content_client_summary', array(__CLASS__, 'content'), 10, 2);
		add_filter('get_objects_reports_client_summary', array(__CLASS__, 'get_objects'), 10, 3);
	}
	/**
	* Print HTML before content on report page.
	* @param $request Array of values the $_REQUEST filtered.
	* @param $ranges Array of ranges on timestamp to get objects.
	*/
	public static function before_content($request, $ranges) {
		
	}
	/**
	* Print HTML on report page content.
	* @param $request Array of values the $_REQUEST filtered.
	* @param $ranges Array of ranges on timestamp to get objects.
	*/
	public static function content($request, $ranges) {
		$setting_system = get_option('fakturo_system_options_group', false);
		$currencyDefault = get_fakturo_term($setting_system['currency'], 'fktr_currencies');
		if (is_wp_error($currencyDefault)) {
			echo '<p>'.__( 'Client Summary needs the default currency on system settings.', FAKTURO_TEXT_DOMAIN ).'</p>';
			return true;
		}
		self::get_form_filters($request);
		$objects = reports::get_objects($request, $ranges);
		$html_client_data = '';
		if (is_numeric($request['client_id']) && $request['client_id'] > 0) {
			$client_data = fktrPostTypeClients::get_client_data($request['client_id']);
			//print_r($client_data);
			$html_client_data = '<table class="wp-list-table widefat fixed striped posts" style="width:40%;">
				<thead>
				<tr>
					<td>
						'.__('Client Data', FAKTURO_TEXT_DOMAIN).'
					</td>
					<td>
						
					</td>
				</tr>
				</thead>
				<tbody id="the-list">
					<tr>
					<td>
						'.__('Name', FAKTURO_TEXT_DOMAIN).'
					</td>
					<td>
						'.$client_data['post_title'].'
					</td>
					
				</tr>
				</tbody>
			</table>';
		}
		echo $html_client_data;
		$html_objects = '<h2>No results with this filters</h2>';
		if (!empty($objects)) {
			$html_objects = '<table class="wp-list-table widefat fixed striped posts">
				<thead>
				<tr>
					<td>
						'.__('Date', FAKTURO_TEXT_DOMAIN).'
					</td>
					<td>
						'.__('Type', FAKTURO_TEXT_DOMAIN).'
					</td>
					<td>
						'.__('Reference', FAKTURO_TEXT_DOMAIN).'
					</td>
					<td>
						'.__('Subtotal', FAKTURO_TEXT_DOMAIN).'
					</td>
					<td>
						'.__('Tax', FAKTURO_TEXT_DOMAIN).'
					</td>
					<td>
						'.__('Total', FAKTURO_TEXT_DOMAIN).'
					</td>
				</tr>
				</thead>
				<tbody id="the-list">';

			$documents_values = array();
			$total_documents = array('subtotal' => 0, 'total' => 0);
			$default_document = array('subtotal' => 0, 'total' => 0);
			$documents_values['Receipts'] = $default_document;
			$array_taxes = array();
			foreach ($objects as $obj) {

				$obj_type = '';
				$obj_link = admin_url('post.php?post='.$obj->ID.'&action=edit');
				$subtotal_print = 0;
				$total_print = 0;
				$tax = 0;
				if ($obj->post_type=='fktr_sale') {
					$object_data = fktrPostTypeSales::get_sale_data($obj->ID);

					$discriminates_taxes = false;
					if ($object_data['invoice_type'] > 0) {
						$term_invoice_type = get_fakturo_term($object_data['invoice_type'], 'fktr_invoice_types');		
						if (!is_wp_error($term_invoice_type)) {
							if ($term_invoice_type->discriminates_taxes) {
								$discriminates_taxes = true;
							}
						} else {
							continue;
						}
					}

					$sum = false;
					if (!empty($term_invoice_type->sum)) {
						$sum = true;
					}
					if (!isset($documents_values[$term_invoice_type->name])) {
						$documents_values[$term_invoice_type->name] = $default_document;
					}

					if ($discriminates_taxes) {
						if (!empty($object_data['taxes_in_products'])) {
							foreach ($object_data['taxes_in_products'] as $key => $value) {
								$taxPorcent = 0;
								$taxName = 'Tax';
								if ($key > 0) {
									$term_tax = get_fakturo_term($key, 'fktr_tax');
									if(!is_wp_error($term_tax)) {
										$taxPorcent = $term_tax->percentage;
										$taxName = $term_tax->name;
									} 
								} else {
									if ($object_data['client_data']['tax_condition'] > 0) {
										$term_tax_condition = get_fakturo_term($object_data['client_data']['tax_condition'], 'fktr_tax_conditions');
										if(!is_wp_error($term_tax_condition)) {
											if ($term_tax_condition->overwrite_taxes) {
												//$taxPorcent = $term_tax_condition->tax_percentage;
											}
										} 
									}
								}
								if (!isset($array_taxes[$key])) {
									$array_taxes[$key] = array('name' =>$taxName, 'total' => 0, 'porcent' => $taxPorcent, 'id' => $key);
								}
								if (!isset($default_document[$key.'tax'])) { 
									$default_document[$key.'tax'] = 0;
									
									foreach ($documents_values as $kd => $doc) {
										$documents_values[$kd] = array_merge($default_document, $documents_values[$kd]);	
									}
								}

								

								$new_value = fakturo_transform_money($object_data['invoice_currency'], $setting_system['currency'], $value);
								if ($sum) {
									$array_taxes[$key]['total']  = $array_taxes[$key]['total']+$new_value;
									$tax = $tax+$new_value;
									$documents_values[$term_invoice_type->name][$key.'tax'] = $new_value;
								} else {
									$array_taxes[$key]['total']  = $array_taxes[$key]['total']-$new_value;
									$tax = $tax-$new_value;
									$documents_values[$term_invoice_type->name][$key.'tax'] = -$new_value;
								}

								
								//$tax = $tax+$new_value;
								//$htmltaxes .= '<label id="label_tax_in_'.$key.'">'.$taxName.' '.fakturo_porcent_to_mask($taxPorcent).'%:'.(($setting_system['currency_position'] == 'before')?$currencyDefault->symbol.' ':'').''.number_format($value, $setting_system['decimal_numbers'], $setting_system['decimal'], $setting_system['thousand']).''.(($setting_system['currency_position'] == 'after')?' '.$currencyDefault->symbol:'').'</label> <input type="hidden" name="taxes_in_products['.$key.']" value="'.$value.'"/>';
							}
						}


					}


					

					$obj_type = __('Invoice', FAKTURO_TEXT_DOMAIN);
					//$subtotal = fakturo_transform_money($object_data['invoice_currency'], $setting_system['currency'], $object_data['in_sub_total']);
					//$total = fakturo_transform_money($object_data['invoice_currency'], $setting_system['currency'], $object_data['in_total']);
					if ($sum) {
						$subtotal = fakturo_transform_money($object_data['invoice_currency'], $setting_system['currency'], $object_data['in_sub_total']);
						$total = fakturo_transform_money($object_data['invoice_currency'], $setting_system['currency'], $object_data['in_total']);
						
					} else {
						$subtotal = -fakturo_transform_money($object_data['invoice_currency'], $setting_system['currency'], $object_data['in_sub_total']);
						$total = -fakturo_transform_money($object_data['invoice_currency'], $setting_system['currency'], $object_data['in_total']);
						
					}
					$documents_values[$term_invoice_type->name]['subtotal'] = $documents_values[$term_invoice_type->name]['subtotal']+$subtotal;
					$documents_values[$term_invoice_type->name]['total'] = $documents_values[$term_invoice_type->name]['total']+$total;
					$total_documents['subtotal'] = $total_documents['subtotal']+$subtotal;
					$total_documents['total'] = $total_documents['total']+$total;
					
					
					
				
					
					

				} else {
					$object_data = fktrPostTypeReceipts::get_receipt_data($obj->ID);
					$obj_type = __('Receipt', FAKTURO_TEXT_DOMAIN);
					$subtotal = $object_data['total_to_impute'];
					$tax = 0;
					
					$total =  $object_data['total_to_impute'];
					
					$total_documents['subtotal'] = $total_documents['subtotal']+$subtotal;
					$total_documents['total'] = $total_documents['total']+$total;
				}
				
				if (is_numeric($request['client_id']) && $request['client_id'] > 0) {
					if ($object_data['client_id'] != $request['client_id']) {
						continue;
					}
				}
				$subtotal_print = (($setting_system['currency_position'] == 'before')?$currencyDefault->symbol.' ':'').''.number_format($subtotal, $setting_system['decimal_numbers'], $setting_system['decimal'], $setting_system['thousand']).''.(($setting_system['currency_position'] == 'after')?' '.$currencyDefault->symbol:'');
				
				$total_print = (($setting_system['currency_position'] == 'before')?$currencyDefault->symbol.' ':'').''.number_format($total, $setting_system['decimal_numbers'], $setting_system['decimal'], $setting_system['thousand']).''.(($setting_system['currency_position'] == 'after')?' '.$currencyDefault->symbol:'');
				
				$tax_print = '-';
				if ($tax != 0) {
					$tax_print = (($setting_system['currency_position'] == 'before')?$currencyDefault->symbol.' ':'').''.number_format($tax, $setting_system['decimal_numbers'], $setting_system['decimal'], $setting_system['thousand']).''.(($setting_system['currency_position'] == 'after')?' '.$currencyDefault->symbol:'');
				}
				
				$html_objects .= '<tr>
					<td>
						'. date_i18n($setting_system['dateformat'].' '.get_option( 'time_format' ), $obj->timestamp_value).'
					</td>
					<td>
						'.$obj_type.'
					</td>
					<td>
						<a href="'.$obj_link.'" target="_blank">'.$object_data['post_title'].'</a>
					</td>
					<td>
						'.$subtotal_print.'
					</td>
					<td>
						'.$tax_print.'
					</td>
					<td>
						'.$total_print.'
					</td>
				</tr>';
			}
			$html_objects .= '</tbody>
			</table>';
		}
		
		$html_totals_data = '<table class="wp-list-table widefat fixed striped posts" style="width:40%;">
				<thead>
				<tr>
					<td>
						'.__('Documents', FAKTURO_TEXT_DOMAIN).'
					</td>';
		$html_totals_data .= '<td>
						'.__('Subtotal', FAKTURO_TEXT_DOMAIN).'
					</td>';
		foreach ($array_taxes as $tk => $tx) {
			$html_totals_data .= '<td>
						'.$tx['name'].' ('.$tx['porcent'].'%)
					</td>';
		}
		$html_totals_data .= '<td>
						'.__('Total', FAKTURO_TEXT_DOMAIN).'
					</td>';

 
		$html_totals_data .= '</tr>
				</thead>
				<tbody id="the-list">
					';
		foreach ($documents_values as $nd => $valdoc) {
			$html_totals_data .= '<tr>
					<td>
						'.$nd.'
					</td>
					<td>
						'.(($setting_system['currency_position'] == 'before')?$currencyDefault->symbol.' ':'').''.number_format($valdoc['subtotal'], $setting_system['decimal_numbers'], $setting_system['decimal'], $setting_system['thousand']).''.(($setting_system['currency_position'] == 'after')?' '.$currencyDefault->symbol:'').'
					</td>';
					foreach ($array_taxes as $tkd => $txd) {
						$html_totals_data .= '<td>
									'.(($setting_system['currency_position'] == 'before')?$currencyDefault->symbol.' ':'').''.number_format($valdoc[$tkd.'tax'], $setting_system['decimal_numbers'], $setting_system['decimal'], $setting_system['thousand']).''.(($setting_system['currency_position'] == 'after')?' '.$currencyDefault->symbol:'').'
								</td>';
					}

					$html_totals_data .= '<td>
						'.(($setting_system['currency_position'] == 'before')?$currencyDefault->symbol.' ':'').''.number_format($valdoc['total'], $setting_system['decimal_numbers'], $setting_system['decimal'], $setting_system['thousand']).''.(($setting_system['currency_position'] == 'after')?' '.$currencyDefault->symbol:'').'
					</td>
					</tr>';
		}
		
		$html_totals_data .= '<tr>
					<td>
						
					</td>
					<td>
						'.(($setting_system['currency_position'] == 'before')?$currencyDefault->symbol.' ':'').''.number_format($total_documents['subtotal'], $setting_system['decimal_numbers'], $setting_system['decimal'], $setting_system['thousand']).''.(($setting_system['currency_position'] == 'after')?' '.$currencyDefault->symbol:'').'
					</td>';
					foreach ($array_taxes as $tk => $tx) {
						$html_totals_data .= '<td>
									'.(($setting_system['currency_position'] == 'before')?$currencyDefault->symbol.' ':'').''.number_format($tx['total'], $setting_system['decimal_numbers'], $setting_system['decimal'], $setting_system['thousand']).''.(($setting_system['currency_position'] == 'after')?' '.$currencyDefault->symbol:'').'
								</td>';
					}
					

					$html_totals_data .= '<td>
						'.(($setting_system['currency_position'] == 'before')?$currencyDefault->symbol.' ':'').''.number_format($total_documents['total'], $setting_system['decimal_numbers'], $setting_system['decimal'], $setting_system['thousand']).''.(($setting_system['currency_position'] == 'after')?' '.$currencyDefault->symbol:'').'
					</td>
					</tr>';


		$html_totals_data .= '
				</tbody>
			</table>';
		echo '<div style="width: 100%;">
		'.$html_objects.'
		'.$html_totals_data.'
		</div>';
	}
	public static function get_form_filters($request) {

		$selectClients = fakturo_get_select_post(array(
											'echo' => 0,
											'post_type' => 'fktr_client',
											'show_option_none' => __('All clients', FAKTURO_TEXT_DOMAIN ),
											'name' => 'client_id',
											'id' => 'client_id',
											'class' => '',
											'selected' => $request['client_id']
										));

		$array_range = array();
		$array_range['today'] = __( 'Today', FAKTURO_TEXT_DOMAIN );
		$array_range['yesterday'] = __( 'Yesterday', FAKTURO_TEXT_DOMAIN );
		$array_range['this_week'] = __( 'This Week', FAKTURO_TEXT_DOMAIN );
		$array_range['last_week'] = __( 'Last Week', FAKTURO_TEXT_DOMAIN );
		$array_range['this_month'] = __( 'This Month', FAKTURO_TEXT_DOMAIN );
		$array_range['last_month'] = __( 'Last Month', FAKTURO_TEXT_DOMAIN );
		$array_range['this_quarter'] = __( 'This Quarter', FAKTURO_TEXT_DOMAIN );
		$array_range['last_quarter'] = __( 'Last Quarter', FAKTURO_TEXT_DOMAIN );
		$array_range['this_year'] = __( 'This Year', FAKTURO_TEXT_DOMAIN );
		$array_range['last_year'] = __( 'Last Year', FAKTURO_TEXT_DOMAIN );
		$array_range['other'] = __( 'Custom', FAKTURO_TEXT_DOMAIN );
		/*
		* These filters can be used to add or update range values on select html.
		*/
		$array_range = apply_filters('report_filters_range', $array_range, $request);

		$select_range_html = '<select name="range" id="range">';
		foreach ($array_range as $key => $value) {
			$select_range_html .= '<option value="'.$key.'" '.selected($key, $request['range'], false).'>'.$value.'</option>';
		}
		$select_range_html .= '</select>';

		$return_html = '<div id="div_filter_form" style="padding:5px;">
			<form name="filter_form" method="get" action="'.admin_url('admin.php').'">
				<input type="hidden" name="page" value="fakturo_reports"/>
				<input type="hidden" name="sec" value="'.$request['sec'].'"/>
				'.$select_range_html.'
				'.$selectClients.'
				<input type="submit" class="button-secondary" value="'.__( 'Filter', FAKTURO_TEXT_DOMAIN ).'"/>
			</form>
		</div>';

		echo $return_html;
	}
	/**
	* Print HTML on report page content.
	* @global wpdb $wpdb WordPress database abstraction object.
	* @param Array $return to update or add new objects.
	* @param Array $request of values the $_REQUEST filtered.
	* @param Array $ranges of ranges on timestamp to get objects.
	* @return Array of objects.
	*/
	public static function get_objects($return, $request, $ranges) {
		global $wpdb;
		$sql = sprintf("SELECT p.ID, pm.meta_key, pm.meta_value as timestamp_value, p.post_type as post_type FROM {$wpdb->posts} as p
		LEFT JOIN {$wpdb->postmeta} as pm ON p.ID = pm.post_id
        WHERE 
        pm.meta_key = 'date'
		AND p.post_status = 'publish'
		AND (p.post_type = 'fktr_sale' OR p.post_type = 'fktr_receipt')
		AND pm.meta_value >= '%s'
		AND pm.meta_value < '%s'
        GROUP BY p.ID 
		", $ranges['from'], $ranges['to']);
		$results = $wpdb->get_results($sql, OBJECT);
		if (!empty($results)) {
			$return = $results;
		}
		return $return;
	}
}
/**
 * Execute all hooks on client_summmary
 */
client_summmary::hooks();
?>