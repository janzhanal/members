<?php if (!defined("__HIDE_TEST__")) exit; /* zamezeni samostatneho vykonani */ ?>
<?
require_once './modify_log.inc.php';

if (!defined('EMAIL_ENDL')) define('EMAIL_ENDL', "\n");

/**
 * library for payments
*/

/*
 * vytvor reklamaci platby
 */
function createClaim($user_id, $payment_id, $claim_text)
{
	$user_id = (IsSet($user_id) && is_numeric($user_id)) ? (int)$user_id : 0;
	$payment_id = (IsSet($payment_id) && is_numeric($payment_id)) ? (int)$payment_id : 0;
	$claim_text = correct_sql_string($claim_text);
	$query = "insert into ".TBL_CLAIM." (user_id, payment_id, text) 
		values (".$user_id.", ".$payment_id.", '".$claim_text."')";
	query_db($query);
	$query = "update ".TBL_FINANCE." set claim = 1 where id = $payment_id";
	query_db($query);
}

/*
 * edituj reklamaci
 */
function updateClaim($claim_id, $claim_text)
{
	$claim_id = (IsSet($claim_id) && is_numeric($claim_id)) ? (int)$claim_id : 0;
	$claim_text = correct_sql_string($claim_text);
	$query = "update ".TBL_CLAIM." set text='".$claim_text."' where id = $claim_id";
	query_db($query);
}

/*
 * uzavri reklamaci
 */
function closeClaim($claim_id, $payment_id)
{
	$claim_id = (IsSet($claim_id) && is_numeric($claim_id)) ? (int)$claim_id : 0;
	$payment_id = (IsSet($payment_id) && is_numeric($payment_id)) ? (int)$payment_id : 0;
	$query = "update ".TBL_FINANCE." set claim = 0 where id = $payment_id";
	query_db($query);
}

/*
 * vrat pole [{id, email, name}] pro dany user_id; prazdne pole pokud neexistuje nebo nema platny email
 */
function _GetClaimRecipient($user_id)
{
	$user_id = (IsSet($user_id) && is_numeric($user_id)) ? (int)$user_id : 0;
	if ($user_id <= 0) return array();

	$query = "select id, email, sort_name from ".TBL_USER." where id = $user_id limit 1";
	$result = query_db($query);
	$user = mysqli_fetch_array($result);
	if (!$user || !IsValidEmail($user['email'])) return array();

	return array(array('id' => (int)$user['id'], 'email' => $user['email'], 'name' => $user['sort_name']));
}

/*
 * vrat pole [{id, email, name}] pro vsechny financniky (policy_fin = 1) s platnym emailem
 */
function _GetFinanceAdminRecipients()
{
	$recipients = array();
	$query = "select u.id, u.email, u.sort_name from ".TBL_USER." u inner join ".TBL_ACCOUNT." a on a.id_users = u.id where a.policy_fin = 1";
	$result = query_db($query);
	if ($result)
	{
		while ($user = mysqli_fetch_array($result))
		{
			if (IsValidEmail($user['email']))
				$recipients[] = array('id' => (int)$user['id'], 'email' => $user['email'], 'name' => $user['sort_name']);
		}
	}
	return $recipients;
}

/*
 * posli email o zalozeni reklamace (vlastnik plati) nebo o odpovedi na ni (financnik odpovida)
 * $actor_user_id - kdo pridal novy radek do vlakna reklamace (createClaim, ne updateClaim)
 */
function NotifyClaimEvent($payment_id, $actor_user_id)
{
	global $g_baseadr, $g_fullname, $g_emailadr;

	$payment_id = (IsSet($payment_id) && is_numeric($payment_id)) ? (int)$payment_id : 0;
	$actor_user_id = (IsSet($actor_user_id) && is_numeric($actor_user_id)) ? (int)$actor_user_id : 0;

	$query = "select amount, note, date, id_users_user, id_users_editor from ".TBL_FINANCE." where id = $payment_id limit 1";
	$result = query_db($query);
	$payment = mysqli_fetch_array($result);
	if (!$payment) return;

	$owner_id = (int)$payment['id_users_user'];
	$editor_id = (int)$payment['id_users_editor'];

	$result_claim = query_db("select text from ".TBL_CLAIM." where payment_id = $payment_id order by date desc limit 1");
	$claim = mysqli_fetch_array($result_claim);
	$claim_text = $claim ? $claim['text'] : '';

	$payment_desc = 'Datum: '.formatDate($payment['date']).EMAIL_ENDL.'Částka: '.$payment['amount'].EMAIL_ENDL.'Poznámka: '.$payment['note'].EMAIL_ENDL;
	$link = $g_baseadr.'claim.php?payment_id='.$payment_id;

	if ($actor_user_id == $owner_id)
	{
		$recipients = ($editor_id > 0) ? _GetClaimRecipient($editor_id) : _GetFinanceAdminRecipients();
		$subject = 'Reklamace platby';
		$msg = 'Dobrý den,'.EMAIL_ENDL.EMAIL_ENDL.'byla založena/aktualizována reklamace platby:'.EMAIL_ENDL.EMAIL_ENDL
			.$payment_desc.EMAIL_ENDL.'Text reklamace:'.EMAIL_ENDL.$claim_text.EMAIL_ENDL.EMAIL_ENDL
			.'Detail a odpověď: '.$link.EMAIL_ENDL;
	}
	else
	{
		$recipients = _GetClaimRecipient($owner_id);
		$subject = 'Odpověď na reklamaci platby';
		$msg = 'Dobrý den,'.EMAIL_ENDL.EMAIL_ENDL.'na vaši reklamaci platby přišla odpověď:'.EMAIL_ENDL.EMAIL_ENDL
			.$payment_desc.EMAIL_ENDL.'Odpověď:'.EMAIL_ENDL.$claim_text.EMAIL_ENDL.EMAIL_ENDL
			.'Detail: '.$link.EMAIL_ENDL;
	}

	foreach ($recipients as $recipient)
	{
		if ($recipient['id'] == $actor_user_id) continue; // neposilej sam sobe
		SendEmail($g_fullname, $g_emailadr, $recipient['name'], $recipient['email'], $msg, $subject);
	}
}

/*
 * posli email o uzavreni reklamace vlastnikovi platby
 */
function NotifyClaimClosed($payment_id, $actor_user_id)
{
	global $g_baseadr, $g_fullname, $g_emailadr;

	$payment_id = (IsSet($payment_id) && is_numeric($payment_id)) ? (int)$payment_id : 0;
	$actor_user_id = (IsSet($actor_user_id) && is_numeric($actor_user_id)) ? (int)$actor_user_id : 0;

	$query = "select amount, note, date, id_users_user from ".TBL_FINANCE." where id = $payment_id limit 1";
	$result = query_db($query);
	$payment = mysqli_fetch_array($result);
	if (!$payment) return;

	$owner_id = (int)$payment['id_users_user'];
	if ($owner_id == $actor_user_id) return; // neposilej sam sobe

	$payment_desc = 'Datum: '.formatDate($payment['date']).EMAIL_ENDL.'Částka: '.$payment['amount'].EMAIL_ENDL.'Poznámka: '.$payment['note'].EMAIL_ENDL;
	$link = $g_baseadr.'claim.php?payment_id='.$payment_id;

	$subject = 'Reklamace platby vyřešena';
	$msg = 'Dobrý den,'.EMAIL_ENDL.EMAIL_ENDL.'vaše reklamace platby byla uzavřena:'.EMAIL_ENDL.EMAIL_ENDL
		.$payment_desc.EMAIL_ENDL.'Detail: '.$link.EMAIL_ENDL;

	foreach (_GetClaimRecipient($owner_id) as $recipient)
	{
		SendEmail($g_fullname, $g_emailadr, $recipient['name'], $recipient['email'], $msg, $subject);
	}
}

/*
 * vytvor platbu
 * params: amount, user_id (target of money) ...
 *
*/
function createPayment($editor_id, $user_id, $amount, $note, $datum, $id_zavod)
{
	global $db_conn;

	$editor_id = (IsSet($editor_id) && is_numeric($editor_id)) ? (int)$editor_id : 0;
	$user_id = (IsSet($user_id) && is_numeric($user_id)) ? (int)$user_id : 0;
	$amount = (IsSet($amount) && is_numeric($amount)) ? (int)$amount : 0;
	if ($datum==null)
		$datum=date("Y-m-d");
	else
		$datum = String2SQLDateDMY($datum);
	if ($id_zavod == null)
		$id_zavod = 'NULL'; // php null to sql null
	$note = correct_sql_string($note);
	$query = "insert into ".TBL_FINANCE." (id_users_editor, id_users_user, amount, note, date, id_zavod) values 
			(".$editor_id.", ".$user_id.", ".$amount.", '".$note."', '".$datum."', ".$id_zavod.")";
	query_db($query);
	$lastId = mysqli_insert_id($db_conn);
	SaveItemToModifyLog_Add(TBL_FINANCE, "id=$lastId|user_id=$user_id|amount=$amount");
	return $lastId;
}

/*
 * nastav platbu jako stornovanou
 */
function stornoPayment($editor_id, $trn_id, $storno_note)
{
	global $db_conn;

	$editor_id = (IsSet($editor_id) && is_numeric($editor_id)) ? (int)$editor_id : 0;
	$trn_id = (IsSet($trn_id) && is_numeric($trn_id)) ? (int)$trn_id : 0;
	$datum=date("Y-m-d");
	$storno_note = correct_sql_string($storno_note);
	$query = "update ".TBL_FINANCE." set storno='1', storno_by=".$editor_id.", storno_note='".$storno_note."', storno_date = '".$datum."' where id = $trn_id";
	query_db($query);
	SaveItemToModifyLog_Add(TBL_FINANCE, "id=$trn_id|note=$storno_note");
}

function updatePayment($editor_id, $trn_id, $id_zavod, $amount, $note)
{
	global $db_conn;

	$editor_id = (IsSet($editor_id) && is_numeric($editor_id)) ? (int)$editor_id : 0;
	$trn_id = (IsSet($trn_id) && is_numeric($trn_id)) ? (int)$trn_id : 0;
	$id_zavod = (IsSet($id_zavod) && is_numeric($id_zavod)) ? (int)$id_zavod : 'NULL';
	$amount = (IsSet($amount) && is_numeric($amount)) ? (int)$amount : 0;
	$note = correct_sql_string($note);
	$query = "update ".TBL_FINANCE." set id_zavod=".$id_zavod.", amount=".$amount.", note='".$note."' where id = $trn_id";
	query_db($query);
	SaveItemToModifyLog_Edit(TBL_FINANCE, "id=$trn_id|user_id=$editor_id|amount=$amount|note=$note");
}

/*
 * vraci flatrate pro zadane user_id
 * v budoucnu pouzit pro vraceni informace, zda user neni i sponsor
*/
/* not used - undefined flatrate & percents
function getUserPaymentMethod($id)
{
	//select flatrate, percents from user where id = $id;
	$paymentMethod['rate'] = $flatrate;
	$paymentMethod['percent'] = $percents;
	return $paymentMethod;
}
*/
/*
 * vraci -1 pro flatrate, jinak procenta v desetinne podobe
*/
/* not used - undefined flatrate & percents
function getUserPercent($id)
{
	//TODO popremyslet, zda by nebylo lepsi vracet false (nebo -1, null?) v pripade, kdy je user na flatrate
	//TODO nebo rovnou nespojit s getUserPaymentMethod, kdy by vracela False a zaroven i vysi procent
	$select = "select flatrate, percents from '".TBL_USER."' where id = $id";
	
	if ($flatrate) return -1;
	return $percent;
}
*/
/* not used - undefined csos
function getCSOSFlag($id)
{
	//select csos from race where id = $id;
	return $csos;
}
*/
/* 
 * hisotrizuj platby
 * ve sloupci fin v user bude suma historizovanych plateb
 *
*/
function historizePaymentsForUser($to_date, $user_id)
{
	//navys sloupec fin z tabulky user o vysi plateb probehlych do $to_date
	//update user set fin = fin + (select sum(amount) from finance where user = $user_id and date <= $to_date and history = 0) where user = $user_id;
	//historizace spoctenych plateb 
	//update finance set history = 1 where user = $user_id and date <= $to_date and history = 0;
}

/*
 * prepocte historizovane zaznamy a ulozi uzivateli
 * storno platby se nepocitaji
*/
function recalculateHistory($user_id)
{
	//update user set fin = (select sum(amount) from finance where user_id = $user_id and history = 1 and storno = 0) where id = $user_id;
}

// /*
//  * storno platby
//  * pokud je platba historizovana, pak prepocti historii
// */
// function stornoPayment($id)
// {
// 	//update finance set storno = 1 where id = $id;
// 	//select user_id, history from payment where id = $id;
// 	if ($history)
// 	{
// 		recalculateHistory($user_id);
// 	}
// }

/*
 * vrati pole zustatku pro vsechny uzivatele
 * sloupce ve vracenem poli : id, fin_total, prijmeni, jmeno
*/
function getAllUsersCurrentBalance()
{
	global $db_conn;

	$query = 'SELECT u.id, hidden, prijmeni,jmeno, ifnull(f.sum_amount,0) sum_amount, (n.amount+f.sum_amount) total_amount, u.chief_pay FROM '.TBL_USER.' u 
		left join (select sum(fin.amount) sum_amount, id_users_user from '.TBL_FINANCE.' fin where (fin.storno is null) group by fin.id_users_user) f on u.id=f.id_users_user 
		left join (select ui.chief_pay payer_id, ifnull(sum(fi.amount),0) amount from '.TBL_USER.' ui 
		left join '.TBL_FINANCE.' fi on fi.id_users_user = ui.id where ui.chief_pay is not null and (fi.storno is null or fi.storno != 1) group by ui.chief_pay) n on u.id=n.payer_id 
		left join '.TBL_FINANCE_TYPES.' ft on ft.id = u.finance_type
		group by u.id ORDER BY u.`sort_name` ASC;';
		
	$vysl=query_db($query);
	$data = array();
	if ($vysl != FALSE)
	{
		while ($zazn=mysqli_fetch_array($vysl))
		{
			if (($zazn['chief_pay']>0 && $zazn['chief_pay']<>$zazn['id']) || $zazn['hidden'])
			{
				// pokud za nej plati nekdo jiny, vubec nebrat v potaz !
				// nebo pokud je skryt
			}
			else
			{
				$data[$zazn['id']] = $zazn;
				$data[$zazn['id']]['fin_total'] = $zazn['sum_amount'];
				
				if ($zazn['total_amount'] != null )
					$data[$zazn['id']]['fin_total'] = $zazn['total_amount'];
			}
		}
	}
	else
		return array();
	
	return $data;
}

/*
 * pridani informace, kdo komu penize poslal, pridava se do poznamky
*/
function createFinanceNoteFromTo($lid_from, $lid_to)
{
	global $db_conn;

	$lid_from = (IsSet($lid_from) && is_numeric($lid_from)) ? (int)$lid_from : 0;
	$lid_to = (IsSet($lid_to) && is_numeric($lid_to)) ? (int)$lid_to : 0;

	//TODO popremyslet, zda nerozdelit do 2 selectu, takhle je to zbytecne necitelne

	//nutno delat spojenim 2 selectu, aby bylo zachovano poradi na vystupu nejdrive from a pote to
	$select = "SELECT sort_name name FROM ".TBL_USER." WHERE id = $lid_from UNION SELECT sort_name name FROM ".TBL_USER." WHERE id = $lid_to";
	$vysledek_name_from_name_to = query_db($select);
	$zaznam_from = mysqli_fetch_array($vysledek_name_from_name_to);
	$zaznam_to = mysqli_fetch_array($vysledek_name_from_name_to);
	return " <i>[".$zaznam_from['name']."->".$zaznam_to['name']."]</i> ";
}

?>