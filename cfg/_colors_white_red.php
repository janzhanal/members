<?
// background: soft off-white (main content)
// nav column: soft charcoal (not pure black - avoids the harsh max-contrast eye strain of true #000/#FFF side by side)
// accent: red
// inspired by https://zabiny.club (bg #fff, text #222, accent #e03a35)
// two red shades: #CC2A26 for text/links on light bg (>=4.5:1 AA contrast), #E03A35 for hover/larger blocks and text-on-dark

//==================================================================
// colors cfg file
//==================================================================
if (!defined('PHP_GLOBAL_COLORS_INCLUDED'))
{
	define('PHP_GLOBAL_COLORS_INCLUDED', 1);

//==================================================================
// basic colors
//==================================================================

	$g_colors['body_text'] = '#2B2B2B';
	$g_colors['body_bgcolor'] = '#F7F6F5';

	$g_colors['nav_bgcolor_out'] = '#262626';
	$g_colors['nav_bgcolor_in'] = '#333333';
	$g_colors['nav_bgcolor_selected'] = '#CC2A26';
	$g_colors['nav_bgcolor_group_header'] = '#333333';

	$g_colors['nav_link'] = '#E8E8E6';
	$g_colors['nav_link_hover'] = '#FF6B5E';
	$g_colors['nav_group_header'] = '#FF6B5E';

	$g_colors['nav_item_selected'] = '#F5F5F5';
	$g_colors['nav_item_selected_border'] = '#F5F5F5';
	$g_colors['nav_bgcolor_item_selected'] = '#CC2A26';

	$g_colors['news_item_text'] = '#2B2B2B';
	$g_colors['news_item_date'] = '#999999';
	$g_colors['news_item_title'] = '#222222';
	$g_colors['news_last_date'] = '#999999';
	$g_colors['news_item_author'] = '#777777';

	$g_colors['nav_member_text'] = '#E8E8E6';

	$g_colors['form_data_value'] = '#1A1A1A';
	$g_colors['form_data_error'] = '#CC2A26';

	$g_colors['body_link'] = '#CC2A26';
	$g_colors['body_link_visited'] = '#8A1F1C';
	$g_colors['body_link_hover'] = '#FF6B5E';

	$g_colors['erase_link'] = '#CC2A26';

	$g_colors['address_link'] = '#2B2B2B';
	$g_colors['address_link_hover'] = '#CC2A26';

	$g_colors['highlight_text'] = '#CC2A26';

	$g_colors['input_text'] = '#2B2B2B';
	$g_colors['input_border'] = '#CCCCCC';
	$g_colors['input_bgcolor'] = '#FFFFFF';
	$g_colors['input_bgcolor_focus'] = '#FDF0EF';

//---------------------------- new -------------------

	$g_colors['body_hr_line'] = '#CC2A26';
	$g_colors['nav_hr_line'] = '#5C2624';

	$g_colors['disable_text'] = '#999999';
	$g_colors['warning_text'] = '#CC2A26';

	$g_colors['footer_text'] = '#999999';
	$g_colors['version_text'] = '#CCCCCC';

	$g_colors['result_text'] = '#1A1A1A';

//==================================================================
// table colors
//==================================================================

	$g_colors['table_header'] = '#333333';
	$g_colors['table_row1'] = '#FFFFFF';
	$g_colors['table_row2'] = '#F7F7F7';
	$g_colors['table_row_highlight'] = '#FCEAE9';
	$g_colors['table_row_select'] = '#F8C9C7';
	$g_colors['table_text'] = '#2B2B2B';
	$g_colors['table_text_header'] = '#F5F5F5';
	$g_colors['table_text_highlight'] = '#CC2A26';

	$g_colors['table_cal_border'] = '#CCCCCC';
	$g_colors['table_cal_weekend'] = '#F5DEDD';
	$g_colors['table_cal_race'] = '#CC2A26';
	$g_colors['table_cal_normal'] = '#FFFFFF';
	$g_colors['table_cal_today'] = '#333333';
	$g_colors['table_cal_today_text'] = '#F5F5F5';
	$g_colors['table_cal_empty'] = '#F2F2F2';

//==================================================================
// race type colors
//==================================================================

	$g_colors['r_type0_Z'] = '#1A1A1A';
	$g_colors['r_type0_T'] = '#777777';
	$g_colors['r_type0_S'] = '#777777';
	$g_colors['r_type0_V'] = '#777777';
	$g_colors['r_type0_N'] = '#777777';
	$g_colors['r_type0_J'] = '#777777';

}	// define (PHP_GLOBAL_COLORS_INCLUDED)
?>
