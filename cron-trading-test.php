<?php
//declare(strict_types=1);
date_default_timezone_set('Africa/Johannesburg');

require_once('db.php');
require_once('valr-test.php');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
$aPost = $_REQUEST;
$iHr = isset($aPost['h']) ? $aPost['h'] : date('H');
$iMin = isset($aPost['i']) ? $aPost['i'] : date('i');
$iDay = isset($aPost['d']) ? $aPost['d'] : date('d');
$iTimestamp = mktime( $iHr, $iMin, 0, date('m'),$iDay,date('Y') ); // 1715553906; //time();
$iTimeToConsider = $iTimestamp - (60*60*12);// mktime( date('H')-12, date('i'), 0, date('m'),date('d'),date('Y') );
#$myfile = fopen("btc.txt", "a+") or die("Unable to open file!");

$sql = $sql2 = "select coinlore_id, symbol, preferred_pair, my_rating from cryptocurrencies 
	 WHERE 1=1 -- exchange='VALR' and (is_active is null or is_active=1) and (last_updated is null or last_updated<$iTimestamp) ";
if ($aPost['symbol'] != '') $sql .= " AND symbol='$aPost[symbol]'";  //symbol='$aPost[symbol]'
$sql .= " order by my_rating desc";

$aValrCurrencies = $db->query($sql)->fetchAll();
$aValrCurrencies = array_column($aValrCurrencies, null, 'symbol');


$sql = "SELECT id, `symbol`, date_created, time_updated, last_traded_price, percent_change, percent_change_5m, ask_price, bid_price, high_price, low_price, `price_usd`, `percent_change_24h`, `percent_change_1h`, `percent_change_7d`, `price_btc`, `market_cap_usd`, `volume24`
		FROM ticker_history  WHERE date_created>=$iTimeToConsider and date_created<=$iTimestamp";
if ($aPost['symbol'] != '') $sql .= " AND symbol='$aPost[symbol]'"; 
$sql .= " ORDER BY id desc";

$aHistory = $db->query($sql)->fetchAll();
$aAllAssetsHistory = array();
foreach($aHistory as $aRow)
{
	$aAllAssetsHistory[$aRow['symbol']][] = $aRow;
}

//History of crypto transactions I have bought
$sql = "select * from mytrades WHERE status is null order by id desc";
$aMyTrades = $db->query($sql)->fetchAll();
$aMyTradesByAsset = array(); // print_r($aMyTrades);die;
foreach($aMyTrades as $aRow)
{
	$aMyTradesByAsset[$aRow['symbol']][] = $aRow;
}

#$aMarket = $oValr->getMarketSummary();
#if (!empty($aMarket))
#{
		
	foreach($aValrCurrencies as $aRow)
	{
			
		if (in_array($aRow['symbol'], array('ETH', 'BTC'))) continue;
		
		$aAssetHistory = isset($aAllAssetsHistory[$aRow['symbol']]) ? $aAllAssetsHistory[$aRow['symbol']] : array();
		
		if (count($aAssetHistory) <= 24) continue; // Have at least 2 hours of records 
		
		$aLastHistoryData = current($aAssetHistory);
		if ($aLastHistoryData['date_created'] < $iTimestamp-1800) continue; // we want to deal with currrencies we just updated 
		
		//consider buying if there is consitent data over the past 30min 
		$iCount = 1;
		$bDataConsistent = false;
		foreach($aAssetHistory  as $aRow)
		{
			if ($aRow['date_created'] >= $iTimestamp-($iCount*300)) $bDataConsistent = true;
			else 
			{
				$bDataConsistent = false; echo $iCount . ': data not consistent<pre>'; print_r($aAssetHistory); die;
				break;
			}
			if ($iCount++ >= 5) break;
		}
		
		
		$aTestBuyCriteria = array();
		if ($bDataConsistent ==  true) $aTestBuyCriteria = checkBuy($aAssetHistory);
		$aLastTrade = isset($aMyTradesByAsset[$aRow['symbol']]) ? current($aMyTradesByAsset[$aRow['symbol']]) : array();
		
		echo '<pre>BUY: ' . $aRow['symbol'] . '<br />'; print_r($aTestBuyCriteria);echo '</pre>'; 
		
		#If the price is going up, check if Momentum is worth so we can purchase the asset . If it going up 3 times consecutively or 2 times and one sideways 
		if (is_array($aTestBuyCriteria) && $aTestBuyCriteria[0] == 'Passed' )
		{
			//If repeat buy, check the Momentum and increase accordingly
			echo '<pre>BUY: ' . $aRow['symbol'] . '<br />'; print_r($aTestBuyCriteria);echo '</pre>'; 
			
			$iTradedTokens = isset($aLastHistoryData['ask_price']) ? number_format(100 / $aLastHistoryData['ask_price'], 6, '.', '') : 0;
			$iCountStockHeld = isset($aMyTradesByAsset[$aRow['symbol']]) ? count($aMyTradesByAsset[$aRow['symbol']]) : 0;
			#if ($iCountStockHeld >= 1) fwrite($myfile, date('d-m-Y H:i:s') . " REPEAT BUY: - $aRow[symbol]: $iCountStockHeld > 1 && ($aLastTrade[token_trading_price] @ $aLastTrade[date_created] IS MORE THAN LAST PURCHASE $aLastHistoryData[bid_price] @ $aLastHistoryData[time_updated] - SO DO NOT BUY \n");
			if ($iCountStockHeld >= 1)
			{
				if ($aLastTrade['token_trading_price'] >= $aLastHistoryData['bid_price'] ) continue; // Do not buy if price has not improved from last purchase
				$iIncrease = ($aLastHistoryData['bid_price'] - $aLastTrade['token_trading_price']) / $aLastTrade['token_trading_price'] * 100;
				
				if ($iIncrease < 0.5) continue; // Do not buy if price has not improved by mpre than 0.5%
			}			
			$iAmountToInvest = ($iCountStockHeld == 0) ? 100 : $iCountStockHeld * 100;
			$sBuyNotes = serialize($aTestBuyCriteria[1]);
			$sql = "INSERT INTO mytrades (symbol, buy_or_sell, traded_tokens, token_trading_price, total_trading_price, token_market_price, time_created, buy_notes)
					 VALUES 
					('$aRow[symbol]', 'Buy', $iTradedTokens, $aLastHistoryData[ask_price], $iAmountToInvest, $aLastHistoryData[bid_price], $iTimestamp, '$sBuyNotes')";
			#$db->query($sql);
			
		}
		else if (isset($aMyTradesByAsset[$aRow['symbol']])) // && isset($aLastTrade['percent_change_5m']) && $aLastTrade['percent_change_5m'] <= 0 )
		{
			$aTestSellCriteria = checkBuy($aAssetHistory, 'Sell', $aLastTrade);
			echo '<pre>SELL: '. $aRow['symbol'] . '<br />'; print_r($aLastTrade); print_r($aLastHistoryData);print_r($aTestSellCriteria); echo '</pre>'; 
			//If the crypto went down 2 times in a row, then sell 
			//if (isset($aMyTradesByAsset[$aRow['symbol']]) && $iLastChange < 0  && $iSecondLastChange < 0 )
			if (is_array($aTestSellCriteria) && $aTestSellCriteria[0] == 'Passed' )
			{	
				//First buy, then insert into the DB
				$sSellNotes = serialize($aTestSellCriteria[1]);
				foreach($aMyTradesByAsset[$aRow['symbol']] as $aTrade)
				{
					$iTradeProfitLoss = number_format( (($aLastHistoryData['bid_price'] - $aTrade['token_trading_price']) * $aTrade['traded_tokens']), 6, '.','');
					$sql = "UPDATE mytrades SET status='SOLD' ,token_selling_price=$aLastHistoryData[bid_price], profit='$iTradeProfitLoss', time_sold=$iTimestamp, sell_notes ='$sSellNotes'  WHERE symbol='$aRow[symbol]' and status is null and id=$aTrade[id]";
					echo $sql . '<br />'; 
					#$db->query($sql);	
					//fwrite($myfile, date('d-m-Y H:i:s') . " DOWN - $aRow[symbol]: SELL =>  3 TIMES DOWN: $iLastChange < 0  && $iSecondLastChange < 0 \n");
					//echo "DOWN - $aRow[symbol]: SELL =>  3 TIMES DOWN: $iLastChange < 0  && $iSecondLastChange < 0 \n";
				} echo '<pre>'; print_r($aMyTradesByAsset[$aRow['symbol']]);	die;				
			}echo 111;die;

		}	
	}
#}


