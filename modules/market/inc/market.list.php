<?php

/**
 * market module
 *
 * @package market
 * @version 2.5.2
 * @author CMSWorks Team
 * @copyright Copyright (c) CMSWorks.ru, littledev.ru
 * @license BSD
 */

list($usr['auth_read'], $usr['auth_write'], $usr['isadmin']) = cot_auth('market', 'any', 'RWA');
cot_block($usr['auth_read']);

$sort = cot_import('sort', 'G', 'ALP');
$c = cot_import('c', 'G', 'TXT');
$sq = cot_import('sq', 'G', 'TXT');

$maxrowsperpage = ($cfg['market']['cat_' . $c]['maxrowsperpage']) ? $cfg['market']['cat_' . $c]['maxrowsperpage'] : $cfg['market']['cat___default']['maxrowsperpage'];
list($pn, $d, $d_url) = cot_import_pagenav('d', $maxrowsperpage);

/* === Hook === */
foreach (cot_getextplugins('market.list.first') as $pl)
{
	include $pl;
}
/* ===== */

if (!empty($c))
{
	$out['subtitle'] = (!empty($cfg['market']['cat_' . $c]['metatitle'])) ? $cfg['market']['cat_' . $c]['metatitle'] : $cfg['market']['cat___default']['metatitle'];
	$out['subtitle'] = (!empty($out['subtitle'])) ? $out['subtitle'] : $L['market'];
	$out['desc'] = (!empty($cfg['market']['cat_' . $c]['metadesc'])) ? $cfg['market']['cat_' . $c]['metadesc'] : $cfg['market']['cat___default']['metadesc'];
	$out['keywords'] = (!empty($cfg['market']['cat_' . $c]['keywords'])) ? $cfg['market']['cat_' . $c]['keywords'] : $cfg['market']['cat___default']['keywords'];
}
else
{
	$out['subtitle'] = $L['market'];
	$out['desc'] = $cfg['market']['cat___default']['metadesc'];
	$out['keywords'] = $cfg['market']['cat___default']['keywords'];
}

$where = array();
$order = array();

$where['state'] = "item_state=0";

if (!empty($c))
{
	$catsub = cot_structure_children('market', $c);
	$where['cat'] = "item_cat IN ('" . implode("','", $catsub) . "')";
}

if (!empty($sq))
{
	$words = explode(' ', $sq);
	$sqlsearch = '%'.implode('%', $words).'%';

	$where['search'] = "(item_title LIKE '".$db->prep($sqlsearch)."' OR item_text LIKE '".$db->prep($sqlsearch)."')";
}

switch($sort)
{
	case 'costasc':
		$order['cost'] = 'item_cost ASC';
		break;
	
	case 'costdesc':
		$order['cost'] = 'item_cost DESC';
		break;
	
	default:
		$order['date'] = 'item_date DESC';
		break;
}

$list_url_path = array('c' => $c, 'sort' => $sort, 'sq' => $sq);

$mskin = cot_tplfile(array('market', 'list', $structure['market'][$item['item_cat']]['tpl']));
$t = new XTemplate($mskin);

/* === Hook === */
foreach (cot_getextplugins('market.list.query') as $pl)
{
	include $pl;
}
/* ===== */

$where = ($where) ? 'WHERE ' . implode(' AND ', $where) : '';
$order = ($order) ? 'ORDER BY ' . implode(', ', $order) : '';

$totalitems = $db->query("SELECT COUNT(*) FROM $db_market 
	" . $where . "")->fetchColumn();

$sqllist = $db->query("SELECT * FROM $db_market AS m 
	LEFT JOIN $db_users AS u ON u.user_id=m.item_userid 
	" . $where . "
	" . $order . "
	LIMIT $d, " . $maxrowsperpage);

$pagenav = cot_pagenav('market', $list_url_path, $d, $totalitems, $maxrowsperpage);

$catpatharray[] = array(cot_url('market'), $L['market']);

if(!empty($c))
{
	$catpatharray = array_merge($catpatharray, cot_structure_buildpath('market', $c));
}

$catpath = cot_breadcrumbs($catpatharray, $cfg['homebreadcrumb'], true);

$t->assign(array(
	"SEARCH_ACTION_URL" => cot_url('market', '', '', true),
	"SEARCH_SQ" => cot_inputbox('text', 'sq', $sq, 'class="schstring"'),
	"SEARCH_CAT" => cot_market_selectcat($c, 'c'),
	"SEARCH_SORTER" => cot_selectbox($sort, "sort", array('', 'costasc', 'costdesc'), array($L['market_mostrelevant'], $L['market_costasc'], $L['market_costdesc']), false),
	"PAGENAV_PAGES" => $pagenav['main'],
	"PAGENAV_PREV" => $pagenav['prev'],
	"PAGENAV_NEXT" => $pagenav['next'],
	"PAGENAV_COUNT" => $totalitems,
	"CATALOG" => cot_build_structure_market_tree('', array($c), 0),
	"BREADCRUMBS" => $catpath,
	"CATTITLE" => (!empty($c)) ? $structure['market'][$c]['title'] : '',
	"CATDESC" => (!empty($c)) ? $structure['market'][$c]['desc'] : '',
));

$sqllist_rowset = $sqllist->fetchAll();
$sqllist_idset = array();
foreach($sqllist_rowset as $item)
{
	$sqllist_idset[$item['item_id']] = $item['item_alias'];
}

/* === Hook === */
$extp = cot_getextplugins('market.list.loop');
/* ===== */

foreach ($sqllist_rowset as $item)
{
	$jj++;
	$t->assign(cot_generate_usertags($item, 'PRD_ROW_OWNER_'));

	$t->assign(cot_generate_markettags($item, 'PRD_ROW_', $cfg['market']['shorttextlen'], $usr['isadmin'], $cfg['homebreadcrumb']));
	$t->assign(array(
		"PRD_ROW_ODDEVEN" => cot_build_oddeven($jj),
	));

	/* === Hook - Part2 : Include === */
	foreach ($extp as $pl)
	{
		include $pl;
	}
	/* ===== */

	$t->parse("MAIN.PRD_ROWS");
}
/* === Hook === */
foreach (cot_getextplugins('market.list.tags') as $pl)
{
	include $pl;
}
/* ===== */

$t->parse('MAIN');
$module_body = $t->text('MAIN');

