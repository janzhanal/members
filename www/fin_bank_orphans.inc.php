<?php /* financnik - seznam nespárovaných plateb */
if (!defined("__HIDE_TEST__")) exit; /* zamezeni samostatneho vykonani */ ?>
<?
DrawPageTitle('Nespárované bankovní platby');
?>
<CENTER>
<script language="javascript">
<!-- 
	javascript:set_default_size(800,800);
//-->
</script>
<?php

require_once "./common_user.inc.php";
require_once "./ct_renderer_bank_orphans.inc.php";

$date_from = isset($_POST['date_from']) ? $_POST['date_from'] : date('Y-m-d', strtotime('-30 day'));
$date_to = isset($_POST['date_to']) ? $_POST['date_to'] : date('Y-m-d');
$filter_vs = trim($_POST['variable_symbol'] ?? '');
$filter_amount_from = trim($_POST['amount_from'] ?? '');
$filter_amount_to = trim($_POST['amount_to'] ?? '');
$filter_message = trim($_POST['message'] ?? '');

?>
<form method="post" action="index.php?id=<?=_FINANCE_GROUP_ID_;?>&subid=6">
	Od data: <input type="date" name="date_from" value="<?=htmlspecialchars($date_from)?>">
	Do data: <input type="date" name="date_to" value="<?=htmlspecialchars($date_to)?>">
	VS: <input type="text" name="variable_symbol" placeholder="variabilní symbol" value="<?=htmlspecialchars($filter_vs)?>">
	Částka od: <input type="number" name="amount_from" value="<?=htmlspecialchars($filter_amount_from)?>">
	do: <input type="number" name="amount_to" value="<?=htmlspecialchars($filter_amount_to)?>">
	Zpráva: <input type="text" name="message" placeholder="text zprávy plátce" value="<?=htmlspecialchars($filter_message)?>">
	<input type="submit" value="Filtrovat">
</form>
<br>
<?php

$sql_date_from = correct_sql_string($date_from) . ' 00:00:00';
$sql_date_to = correct_sql_string($date_to) . ' 23:59:59';

$where_extra = '';
if ($filter_vs !== '') {
	$where_extra .= " AND variable_symbol LIKE '%".correct_sql_string($filter_vs)."%'";
}
if ($filter_amount_from !== '' && is_numeric($filter_amount_from)) {
	$where_extra .= " AND amount >= ".(float)$filter_amount_from;
}
if ($filter_amount_to !== '' && is_numeric($filter_amount_to)) {
	$where_extra .= " AND amount <= ".(float)$filter_amount_to;
}
if ($filter_message !== '') {
	$where_extra .= " AND originator_message LIKE '%".correct_sql_string($filter_message)."%'";
}

$query = "SELECT id, created_at, amount, currency, variable_symbol, constant_symbol, specific_symbol, originator_message
          FROM ".TBL_BANK_TRANSACTIONS."
          WHERE status = 'ORPHAN'
            AND created_at >= '$sql_date_from'
            AND created_at <= '$sql_date_to'
            $where_extra
          ORDER BY created_at DESC";

@$vysledek=query_db($query);

if ($vysledek != FALSE && mysqli_num_rows($vysledek) > 0)
{
	$zaznamy = mysqli_fetch_all($vysledek, MYSQLI_ASSOC);

	$tbl_renderer = BankOrphanRendererFactory::createTable();
	$tbl_renderer->addColumns('poradi', 'created_at', 'amount', 'currency', 'variable_symbol', 'originator_message', 'moznosti');

	echo $tbl_renderer->render(new html_table_mc(), $zaznamy);
} else {
	echo "Nebyly nalezeny žádné nespárované platby v tomto období.";
}

?>

<BR>
</CENTER>
