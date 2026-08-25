<?php
if (!defined("__HIDE_TEST__")) exit; /* zamezeni samostatneho vykonani */
?>
<?
DrawPageTitle('Historie transakcí');
?>
<CENTER>
<?
require_once('./ct_renderer_fin_history.inc.php');

$limit = 50;
$page = isset($_GET['list_page']) ? (int)$_GET['list_page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$filter_date_from = trim($_GET['date_from'] ?? date('Y-m-d', strtotime('-2 months')));
$filter_date_to = trim($_GET['date_to'] ?? date('Y-m-d'));
$filter_member = trim($_GET['member'] ?? '');
$filter_amount_from = trim($_GET['amount_from'] ?? '');
$filter_amount_to = trim($_GET['amount_to'] ?? '');
$filter_note = trim($_GET['note'] ?? '');
$filter_claim_only = isset($_GET['claim_only']) && $_GET['claim_only'] === '1';

$filter_params = '';
$where_extra = '';

if ($filter_date_from !== '') {
	$where_extra .= " AND f.date >= '".correct_sql_string($filter_date_from)."'";
	$filter_params .= '&date_from='.urlencode($filter_date_from);
}
if ($filter_date_to !== '') {
	$where_extra .= " AND f.date <= '".correct_sql_string($filter_date_to)."'";
	$filter_params .= '&date_to='.urlencode($filter_date_to);
}
if ($filter_member !== '') {
	$member_like = correct_sql_string($filter_member);
	$where_extra .= " AND (u.reg LIKE '%$member_like%' OR u.sort_name LIKE '%$member_like%')";
	$filter_params .= '&member='.urlencode($filter_member);
}
if ($filter_amount_from !== '' && is_numeric($filter_amount_from)) {
	$where_extra .= " AND f.amount >= ".(float)$filter_amount_from;
	$filter_params .= '&amount_from='.urlencode($filter_amount_from);
}
if ($filter_amount_to !== '' && is_numeric($filter_amount_to)) {
	$where_extra .= " AND f.amount <= ".(float)$filter_amount_to;
	$filter_params .= '&amount_to='.urlencode($filter_amount_to);
}
if ($filter_note !== '') {
	$note_like = correct_sql_string($filter_note);
	$where_extra .= " AND f.note LIKE '%$note_like%'";
	$filter_params .= '&note='.urlencode($filter_note);
}
if ($filter_claim_only) {
	$where_extra .= " AND f.claim = 1";
	$filter_params .= '&claim_only=1';
}

if (!function_exists('render_fin_history_pagination')) {
	function render_fin_history_pagination(int $page, int $pages, string $filter_params = '', string $style = 'margin-bottom: 10px;'): void
	{
		echo "<div style='".$style."'>";
		if ($page > 1) {
			echo "<a href='index.php?id="._FINANCE_GROUP_ID_."&subid=7&list_page=".($page - 1).$filter_params."'><< Novější</a> | ";
		} else {
			echo "<< Novější | ";
		}

		echo "Stránka $page z $pages";

		if ($page < $pages) {
			echo " | <a href='index.php?id="._FINANCE_GROUP_ID_."&subid=7&list_page=".($page + 1).$filter_params."'>Starší >></a>";
		} else {
			echo " | Starší >>";
		}
		echo "</div>";
	}
}
?>
<form method="get" action="index.php" style="margin-bottom: 10px;">
	<input type="hidden" name="id" value="<?=_FINANCE_GROUP_ID_;?>">
	<input type="hidden" name="subid" value="7">
	Od data: <input type="date" name="date_from" value="<?=htmlspecialchars($filter_date_from)?>">
	Do data: <input type="date" name="date_to" value="<?=htmlspecialchars($filter_date_to)?>">
	Člen: <input type="text" name="member" placeholder="reg. č. nebo jméno" value="<?=htmlspecialchars($filter_member)?>">
	Částka od: <input type="number" name="amount_from" value="<?=htmlspecialchars($filter_amount_from)?>">
	do: <input type="number" name="amount_to" value="<?=htmlspecialchars($filter_amount_to)?>">
	Popis: <input type="text" name="note" placeholder="text v poznámce" value="<?=htmlspecialchars($filter_note)?>">
	<label><input type="checkbox" name="claim_only" value="1" <?=$filter_claim_only ? 'checked' : ''?>> jen reklamace</label>
	<input type="submit" value="Filtrovat">
	<? if ($filter_params !== ''): ?>
		<a href="index.php?id=<?=_FINANCE_GROUP_ID_;?>&subid=7">Zrušit filtr</a>
	<? endif; ?>
</form>
<?

$count_query = "SELECT COUNT(*) as cnt FROM `".TBL_FINANCE."` f "
		." left join `".TBL_USER."` u on u.id = f.id_users_user "
		." WHERE f.storno is null".$where_extra;
$res_count = query_db($count_query);
$row_count = mysqli_fetch_assoc($res_count);
$total = $row_count['cnt'];
$pages = ceil($total / $limit);
if ($pages == 0) $pages = 1;

$query = "SELECT unix_timestamp(f.date) datum, u.reg as reg, u.sort_name as name, f.id_users_editor, e.sort_name as editor_name, f.amount, f.note, rc.nazev zavod_nazev, "
		." rc.datum zavod_datum FROM `".TBL_FINANCE."` f "
		." left join `".TBL_USER."` u on u.id = f.id_users_user "
		." left join `".TBL_USER."` e on e.id = f.id_users_editor "
		." left join `".TBL_RACE."` rc on f.id_zavod = rc.id where f.storno is null".$where_extra." ORDER BY f.date desc, f.id desc LIMIT $limit OFFSET $offset";
@$vysl=query_db($query)
	or die("Chyba při provádění dotazu do databáze.");

$zaznamy = $vysl ? mysqli_fetch_all($vysl, MYSQLI_ASSOC) : [];

render_fin_history_pagination($page, $pages, $filter_params);

$tbl_renderer = FinanceHistoryRendererFactory::createTable();
$tbl_renderer->addColumns('datum', 'reg', 'name', 'editor_name', 'amount', 'zavod_datum', 'zavod_nazev', 'note');
$tbl_renderer->addBreak(new MonthExpanderDetector());
$tbl_renderer->setRowAttrsExt(MonthExpanderDetector::rowAttrsExtender(...));

echo $tbl_renderer->render(new html_table_mc(), $zaznamy);

render_fin_history_pagination($page, $pages, $filter_params, 'margin-top: 10px;');

?>
<BR>
</CENTER>
