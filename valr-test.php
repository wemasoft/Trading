<?php

class VALR
{
    private $key;
    private $secret;
    
    function __construct($key, $secret)
    {
        $this->key = $key;
        $this->secret = $secret;
		
    }

    function get_balances()
    {
        $balances = $this->call_api('/v1/account/balances');
        
        return $balances;
    }

    public function myAssets()
    {
		//https://api.valr.com/v1/account/balances?excludeZeroBalances=true
        $pairs = $this->call_api('v1/account/balances?excludeZeroBalances=true');
        return $pairs;
    }

    function getMarketSummary()
    {
		//https://api.valr.com/v1/public/marketsummary
        $price = $this->call_api('/v1/public/marketsummary');
        return $price;
    }

    function getMarketData($of='', $to='')
    {
		//$request->setUrl('https://api.valr.com/v1/marketdata/BTCUSDC/tradehistory?skip=0&limit=10');

		//https://api.valr.com/v1/public/marketsummary
        $price = $this->call_api('/v1/marketdata/' . $of . $to . '/tradehistory?skip=0&limit=10');
        //$price = $this->call_api('/v1/marketdata/GALUSDC/tradehistory?skip=0&limit=10');
		return $price;
    }

    function get_price($of='', $to='')
    {
        $price = $this->call_api('/v1/public/' . $of . $to . '/marketsummary');
        return $price; //->lastTradedPrice;
    }

    function get_trades($of, $to, $limit = 100, $skip = 0, $before_id = null)
    {
        $trades = $this->call_api('/v1/public/' . $of . $to . '/trades?limit=' . $limit . '&skip=' . $skip . ($before_id ? '&beforeId=' . $before_id : ''));
        return $trades;
    }

    function get_history($of, $to, $limit = 100, $skip = 0, $before_id = null)
    {
		//  CURLOPT_URL => 'https://api.valr.com/v1/marketdata/BTCUSDC/tradehistory?skip=0&limit=10',

        $trades = $this->call_api('/v1/marketdata/' . $of . $to . '/tradehistory?limit=' . $limit . '&skip=' . $skip); // . ($before_id ? '&beforeId=' . $before_id : '')
        return $trades;
    }

    function get_orders($of, $to)
    {
        $orders = $this->call_api('/v1/public/' . $of . $to . '/orderbook');
        return $orders;
    }

    function get_currencies()
    {
        $currencies = $this->call_api('v1/public/currencies');
        return $currencies;
    }
    function get_pairs()
    {
        $pairs = $this->call_api('v1/public/pairs');
        return $pairs;
    }


    function sign($method, $path, $body = null, $iTimestamp = null)
    {
        if($body != '')
        {
          $body = json_encode($body);
		  $raw = "$iTimestamp{$method}{$path}{$body}";
        }
		else 
			$raw = "$iTimestamp{$method}{$path}";
        
		$signed_payload = hash_hmac('sha512', $raw, $this->secret, false);
        return  $signed_payload;
    }

    function call_api($path, $method = "GET", $body = null)
    {
      if($path[0] != '/')
        $path = '/' . $path;

      if(substr($path, -1) == '/')
        $path = substr($path, 0, strlen($path) - 1);

	 // echo time(); echo '<br />'; echo (new DateTime())->format('Uv');  echo '<br />';
	  $iTimestamp = round(microtime(true) * 1000); //die; //1000 * time();
      $curl = curl_init();
	  //echo "$iTimestamp{$method}{$path}<br />";
	  $sSignature = hash_hmac('sha512', "$iTimestamp{$method}{$path}", $this->secret, false);
	  //echo $sSignature;die;
      switch($method)
      {
        case "GET":
          curl_setopt_array($curl, array(
            CURLOPT_URL => "https://api.valr.com$path",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => array(
              'X-VALR-API-KEY: ' . $this->key,
              'X-VALR-SIGNATURE: ' . $this->sign($method,$path,null,$iTimestamp),
              'X-VALR-TIMESTAMP: ' . $iTimestamp //now()->timestamp * 1000
            ),
          ));
          break;
        case "POST":
          curl_setopt_array($curl, array(
            CURLOPT_URL => "https://api.valr.com$path",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_POST => 1,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => array(
              'X-VALR-API-KEY: ' . $this->key,
              'X-VALR-SIGNATURE: ' . $this->sign($method,$path,$body,$iTimestamp),
              'X-VALR-TIMESTAMP: ' . $iTimestamp //now()->timestamp * 1000
            ),
          ));
          break;
      }
      

      $response = curl_exec($curl);
      
      curl_close($curl);

      $data = json_decode($response);

      if(isset($data->code))
      {
        switch($data->code)
        {
          case -21:
            throw new Exception("VALR: Currency pair not found.");
            break;
          case -93:
            throw new Exception("VALR: Unauthorised.");
            break;
        }
      }

      if($data == null)
      {
		  return null;
        throw new Exception("VALR: Empty Response.");
      }

      //echo '<pre>'; print_r($data); echo '</pre>'; 

      return json_decode($response, true);
    }
	
	function sma($aParams = array())
	{
		$aAmounts = array_column($aParams, 'price');
		
		$n = 10;  //set the array size
		$a = array(1, 2, 3, 4, 5, 6, 7, 8, 9, 10); //input array elements
		$k = 2;    // set the length int
		$sum = 0;
		$movAvg = 0;
		//iterate the loop and check for the condition
		for ($i = 0; $i <= ( $n - $k); $i++) {
		 $sum = 0;            
		 for ($j = $i; $j < $i + $k; $j++) {
		   $sum += $a[$j];     

		 }
		 $movAvg = $sum / $k;
			printf("Simple Moving Average : %.2f \n",$movAvg); //print output
		}
	}

}

function movingAvg($aParams = array(), $iPeriod = 4)
{
	
	#$closes = array(112.82, 117.32, 113.49, 112, 115.355, 115.54, 112.13, 110.34, 106.84, 110.08, 111.81, 107.12, 108.22, 112.28);
	if(!is_array($aParams) || empty($aParams)) return;
	
	#$aMa = (trader_ma($aParams, $iPeriod, TRADER_MA_TYPE macdfix ));
	$aMa = (trader_ma($aParams, $iPeriod, TRADER_MA_TYPE_EMA));
	#$aMa = (trader_ma($aParams, $iPeriod, TRADER_MA_TYPE_SMA));
	#$aMa = (trader_ema ($aParams, $iPeriod, TRADER_MA_TYPE_SMA));
	
	if (is_array($aMa) && !empty($aMa))
	{
		for($i=$iPeriod; $i>=0; $i--) 
		{
			
			if (!isset($aMa[$i]) && isset($aParams[$i])) $aMa[$i] = $aParams[$i];
			//if (!isset($aMa[$i])) array_push($aMa,$aParams[$i]);
		}
		ksort($aMa);
	}
	else
		$aMa = $aParams;
	
	#$aSlope = trader_linearreg_slope($aParams, 12);

	//echo '<pre>'; print_r($aParams);  print_r($aSlope); echo '</pre>'; 
	
	return $aMa;
}


function linearSlope($aParams = array(), $iPeriod = 12)
{
	
	#$closes = array(112.82, 117.32, 113.49, 112, 115.355, 115.54, 112.13, 110.34, 106.84, 110.08, 111.81, 107.12, 108.22, 112.28);
	if(!is_array($aParams) || empty($aParams)) return;
	
	$aSlope = trader_linearreg_slope($aParams, $iPeriod);
	
	return $aSlope;
}


function checkBuy($aHistory = array(), $sBuyOrSell='Buy', $aParams = array(), $sTest = 0)
{
	// https://scanz.com/4-great-ways-to-scan-for-breakouts/ 

	//If currency is below 0.001, multiply by '000
	$aCurrentRow = current($aHistory); 
	if ($aCurrentRow['last_traded_price'] < 0.00001) $iMultiplier = 10000;
	else if ($aCurrentRow['last_traded_price'] < 0.0001) $iMultiplier = 1000;
	else if ($aCurrentRow['last_traded_price'] < 0.001) $iMultiplier = 100;
	else $iMultiplier = 0;
	if ($iMultiplier > 0)
	{
		foreach($aHistory as $iKey => $aRow) $aHistory[$iKey]['last_traded_price'] = $aRow['last_traded_price']*$iMultiplier; 
	}
	
	$aHistoryPrices = array_column($aHistory, 'last_traded_price');
	$aParamsReverse = array_reverse($aHistoryPrices);
	$a5MinChange = array_column($aHistory, 'percent_change_5m');
	#$a5MinChange = array_reverse($a5MinChange);
	$iLastItem = count($aHistoryPrices) - 1;
	$aLastTransaction = current($aHistory);
	
	//Find the Min & Max peice over the period - 24Hrs 
	$iMinimum = min($aHistoryPrices);
	$iMaximum = max($aHistoryPrices);
	$iCurrentPrice = $aParamsReverse[$iLastItem];
	
	//echo "$iPercFromBottom = ( $iCurrentPrice - $iMinimum ) / ($iMaximum - $iMinimum);";die;
	$iDiff = $iMaximum - $iMinimum;
	$iPercFromBottom = ($iDiff > 0) ? number_format(( $iCurrentPrice - $iMinimum ) / $iDiff,2,'.') : 0;
	
	//Calculate gradient over past 4 Hrs
	$iCount = 1;
	$iCountJ = 1;
	$iCountK = 1;
	$aHistoryReverse = array_reverse($aHistory);
	$aGradientDataset = $aGradientDataset4Hr = array();
	$iGradientDataSetStart = $aLastTransaction['date_created'] - (60*60); // Dataset for an hour
	$iGradientDataSetStart4Hr = $aLastTransaction['date_created'] - (60*60*4); // Dataset for an hour
	foreach($aHistoryReverse as $aRow)
	{	
		if ($aRow['date_created'] > $iGradientDataSetStart) 
		{
			$aGradientDataset[] = [$iCountJ++, $aRow['last_traded_price']];
		}
		
		if ($aRow['date_created'] > $iGradientDataSetStart4Hr && $iCount % 4 == 0 ) 
		{
			$aGradientDataset4Hr[] = [$iCountK++, $aRow['last_traded_price']];
		}
		$iCount++;
	}
	$iLinearReg = $iLinearReg4Hr = 0; 
	if (count($aGradientDataset) > 1) $iLinearReg = gradient($aGradientDataset);  
	if (count($aGradientDataset4Hr) > 1) $iLinearReg4Hr = gradient($aGradientDataset4Hr);   
	
	#Consiser MA 
	# If Price is above MA, do not consider to buy. Return false

	$aMovingAvg = movingAvg($aParamsReverse, 6);
	
	//Array(8=>a, 7=>b, 6=>c, 5=>d, 4=>e, 3=>f, 2=>g, 1=>h
	//Change over the past 40min
	$aChangePastHalfHr = array($aParamsReverse[$iLastItem], $aParamsReverse[$iLastItem-1], $aParamsReverse[$iLastItem-2], $aParamsReverse[$iLastItem-3], $aParamsReverse[$iLastItem-3], $aParamsReverse[$iLastItem-4], $aParamsReverse[$iLastItem-5]);
	$aChange[15] = (isset($aParamsReverse[$iLastItem]) && isset($aParamsReverse[$iLastItem-3])) ? ($aParamsReverse[$iLastItem] - $aParamsReverse[$iLastItem-3])/$aParamsReverse[$iLastItem-3]*100 : 0;
	$aChange[30] = (isset($aParamsReverse[$iLastItem]) && isset($aParamsReverse[$iLastItem-6])) ? ($aParamsReverse[$iLastItem] - $aParamsReverse[$iLastItem-6])/$aParamsReverse[$iLastItem-6]*100 : 0;
	$aChange[40] = (isset($aParamsReverse[$iLastItem]) && isset($aParamsReverse[$iLastItem-8])) ? ($aParamsReverse[$iLastItem] - $aParamsReverse[$iLastItem-8])/$aParamsReverse[$iLastItem-8]*100 : 0;
	$aChange[60] = (isset($aParamsReverse[$iLastItem]) && isset($aParamsReverse[$iLastItem-12])) ? ($aParamsReverse[$iLastItem] - $aParamsReverse[$iLastItem-12])/$aParamsReverse[$iLastItem-12]*100 : 0;
	$aChange[120] = end($aParamsReverse) - current($aParamsReverse) /  current($aParamsReverse) * 100;
	
	if ($sBuyOrSell == 'Buy')
	{
		#Buy if:
			// 1. On upward trend, 2. Price is below MA, 3. 
		//If going down, allow it to plateau 
		$aTestResults = array();
		if ($aHistory[0]['percent_change_5m'] <= 0 ) $aTestResults['trend'] = array(false, 'On downward trend - Fail'); // wait a little for plateau //&& $aHistory[0]['percent_change_5m'] > $aHistory[1]['percent_change_5m']
		else $aTestResults['trend'] = array(true, 'Upward trend - Pass'); 
		
		if (end($aParamsReverse) > end($aMovingAvg)) 
		{
			if ($iPercFromBottom < 0.2 && $aChange[15] > 0 && $aChange[30] > 0)
				$aTestResults['ma'] = array(true, "Price is above MA - BUT Price at bottom @$iPercFromBottom & +ve change over 30min - RISK");
			else
				$aTestResults['ma'] = array(false, "Price is above MA - Fail @$iPercFromBottom");
		}
		else $aTestResults['ma'] = array(true, 'Price is below MA - Pass'); 
		
		//echo "if ({$aHistory[0]['percent_change_5m']} < 0 && {$aHistory[0]['percent_change_5m']} > {$aHistory[1]['percent_change_5m']}) return false;<pre>";// print_r($aHistory);   // wait a little for plateau

		$bAtBottomGoingUp = ($iPercFromBottom < 0.3 && $aChange[15] > 0.5) ? true : false;
		
				
		//Do not buy when its at the top
		if ($iPercFromBottom >= 0.5) $aTestResults['top_bottom'] = array(false, "Too close to top @ $iPercFromBottom - Fail");
		else 
		{
			if ($iLinearReg <= 0 && $iLinearReg4Hr < 0)
			{
				//If all past changes over 30min are +ve, take a risk  //30min
				$iChangePastHalfHrMin = min($aChangePastHalfHr);
				if ($iChangePastHalfHrMin > 0) 
					$aTestResults['top_bottom'] = array(TRUE, "Price is on bottom half @ $iPercFromBottom & on Downward linear trend @ $iLinearReg BUT past 30min has no -ve change - RISK");
				else  
					$aTestResults['top_bottom'] = array(false, "Price is on bottom half @ $iPercFromBottom but on Downward linear trend @ $iLinearReg - Fail");
			}
			else if ($iLinearReg4Hr < 0 && $aChange[60] > 0)
				$aTestResults['top_bottom'] = array(true, "Price is on bottom half @ $iPercFromBottom & trending down 4Hr Linear @ $iLinearReg4Hr but 60min is +ve - Pass");
			else
				$aTestResults['top_bottom'] = array(true, "Price is on bottom half @ $iPercFromBottom & upward Linear @ $iLinearReg - Pass");
		}
				
		if ($aChange[30] > 0 ) $aTestResults['30m'] = array(true, "30min @ $aChange[30] are +ve - Pass");
		else 
		{
			if ($iPercFromBottom <= 0.2 && $aChange[30] > 0)
				$aTestResults['30m'] = array(true, "30min @ $aChange[30] are -ve but indicating upward tred with 15m +ve & price on bottom half @$iPercFromBottom - Pass");
			else
				$aTestResults['30m'] = array(false, "30min @ $aChange[30] are -ve - Fail");
		}
		
		if ($aChange[15] > 0 ) $aTestResults['15m'] = array(true, "15m @ $aChange[15] is +ve - Pass");
		else $aTestResults['15m'] = array(false, "15m @ $aChange[15] is -ve - Fail");
		
		//Return true if all Test are True 
		$bPassed = 'Failed';
		foreach($aTestResults as $aResults)
		{
			if($aResults[0] == true) $bPassed = 'Passed';
			else
			{
				$bPassed = 'Failed'; break;
			}
		}
		//echo '<pre>'; print_r($aTestResults); echo '</pre>'; 
		return array($bPassed, array_column($aTestResults, '1'));
	}
	else // SELL
	{
		$aTestResults = array();
		$iTimeHeldSincePurchase = isset($aParams['time_created']) ? $aCurrentRow['date_created'] - $aParams['time_created'] : 0;

		if ($aHistory[0]['percent_change_5m'] <= 0 && $aChange[15] <= 0)
		{
			
			if ($iPercFromBottom > 0.8)
				$aTestResults['plateau'] = array(true, "Plateau with 5m change @ {$aHistory[0]['percent_change_5m']} & 15min @ $aChange[15], at Top @ $iPercFromBottom - Pass"); // wait a little for plateau
			else if ($iLinearReg > 0 && $iTimeHeldSincePurchase > 3600)
				$aTestResults['plateau'] = array(true, "Plateau with 5m change @ {$aHistory[0]['percent_change_5m']} & 15min @ $aChange[15], with linear @ $iLinearReg - Pass"); // wait a little for plateau
			else if ($iTimeHeldSincePurchase > 3600)
				$aTestResults['plateau'] = array(true, "Plateau with 5m change @ {$aHistory[0]['percent_change_5m']} & 15min @ $aChange[15] - Pass"); // wait a little for plateau
		}
		else
			$aTestResults['plateau'] = array(false, "Past 5m change @ {$aHistory[0]['percent_change_5m']} & 15min @ $aChange[15] - Fail"); // wait a little for plateau
		
		//plateau at the top 
		
		//Purpose: sell TO Maximize profit or cut loss 
		if (isset($aParams['token_trading_price']))
		{
			$iPurchasePrice = isset($aParams['token_trading_price']) ? $aParams['token_trading_price'] : 0;
			$iCurrentPrice = end($aParamsReverse);
			$iProfitOrLossFromPurchase = ($iPurchasePrice > 0) ? ($iCurrentPrice - $iPurchasePrice) / $iPurchasePrice * 100 : 0;


			//Continue holding if on upward trend 	
			
			if ($iProfitOrLossFromPurchase < 0)
			{
				if ($iProfitOrLossFromPurchase < -2) //Cut loss: sell immediately
					$aTestResults['loss'] = array(true, "Cut loss @ $iProfitOrLossFromPurchase, - Pass");
				else if ($iLinearReg <= 0 && $iLinearReg4Hr < 0 && $iTimeHeldSincePurchase > 7200) //SELL If movement is still downwards after 2 hours 
					$aTestResults['loss'] = array(true, "Cut loss @ $iProfitOrLossFromPurchase, - 2 HR Downward mvt");
				else
					$aTestResults['loss'] = array(false, "Cut loss @ $iProfitOrLossFromPurchase, - Fail");
			}			
			
			//To maximize profit: If curve is going down SELL or if still on upward trend, keep it alive 
			if ($iProfitOrLossFromPurchase > 0)
			{
				if ($iPercFromBottom > 0.5 &&  $aChange[15] < 0 && $aHistory[0]['percent_change_5m'] <=0 )
					$aTestResults['profit'] = array(true, "Take profit @ $iProfitOrLossFromPurchase & 15m@$aChange[15] & 5m@{$aHistory[0]['percent_change_5m']} - Pass");					
			}
			
			//Trailing 
			$iHighestPrice = 0; 
			foreach($aHistory as $aRow)
			{
				if ($aRow['date_created'] < $aParams['time_created']) break;
				//if ($aRow['token_trading_price'] $iHighestPrice)
				if ($aRow['last_traded_price'] > $iHighestPrice) $iHighestPrice = $aRow['last_traded_price'];
			}
			
			if ($iHighestPrice > 0)
			{
				$iCurrentChangeFromHishestPoint = ($iHighestPrice - $iCurrentPrice);
				$iTrailing = number_format( ($iCurrentChangeFromHishestPoint/$iHighestPrice * 100), 2, '.'); 
				if ($iCurrentChangeFromHishestPoint > 0 && $iTrailing > 2 )
					$aTestResults['trailing'] = array(true, "Trailing: (Take profit) - @ $iTrailing from high of $iHighestPrice - Pass");				
				else
					$aTestResults['trailing'] = array(false, "Trailing: @ $iTrailing => High:$iHighestPrice, Current:$iCurrentPrice - Fail");	
			}			
			
		}

		//Return true if ANY Test are True 
		$bPassed = 'Failed';
		foreach($aTestResults as $aResults)
		{
			if($aResults[0] == true)
			{
				$bPassed = 'Passed'; break;
			}
			else
			{
				$bPassed = 'Failed';
			}
		}
		
		return array($bPassed, array_column($aTestResults, '1'));
		
	}	
}

function gradient($aDataset)
{
	//Count the number of points in the dataset
	$iDatapoints = count( $aDataset );

	//Start a total for the X-values
	$x_values_sum = 0;

	//Start a total for the Y-values
	$y_values_sum = 0;

	//Calculate the X and Y value sums
	foreach( $aDataset as $index => $point )
	{
		$x_values_sum += $point[ 0 ];
		$y_values_sum += $point[ 1 ];

	}

	//Calculate the mean average X value (x bar)
	$x_mean = $x_values_sum / $iDatapoints;
	
	//Calculate the mean average Y value (y bar)
	$y_mean = $y_values_sum / $iDatapoints;

	//Start the total for the "first moment" deviation score
	$xy_first_moment_sum = 0;

	//Start the total sum of squares for the x and y values
	$x_sum_of_squares = 0;
	$y_sum_of_squares = 0;

	foreach( $aDataset as $index => $point )
	{
		//Calculate the X and Y value first moment deviation scores
		$x_first_moment = $point[ 0 ] - $x_mean;
		$y_first_moment = $point[ 1 ] - $y_mean;

		//Increase the xy first moment score; sum (x first moment) * (y first moment)
		//This is the value on the top of the Rxy formula fraction
		$xy_first_moment_sum += $x_first_moment * $y_first_moment;

		//Increase the x and y sums of squares
		$x_sum_of_squares += pow( $x_first_moment, 2 );
		$y_sum_of_squares += pow( $y_first_moment, 2 );
	}

	//Find the square root of the sums of squares for further use
	$root_x_sum_of_squares = sqrt( $x_sum_of_squares );
	$root_y_sum_of_squares = sqrt( $y_sum_of_squares );

	//Calculate the r value (Rxy)
	$r = $root_x_sum_of_squares != 0 && $root_y_sum_of_squares != 0 ? $xy_first_moment_sum / ( $root_x_sum_of_squares * $root_y_sum_of_squares ) : 0;

	//Calculate the x and y variance from the appliccable sums of squares
	$x_variance = $x_sum_of_squares / ( $iDatapoints - 1 );
	$y_variance = $y_sum_of_squares / ( $iDatapoints - 1 );

	//Calculate the x and y standard errors
	$x_standard_error = $root_x_sum_of_squares / $iDatapoints;
	$y_standard_error = $root_y_sum_of_squares / $iDatapoints;

	//Calculate the standard deviations from the x and y variances
	$x_standard_deviation = sqrt( $x_variance );
	$y_standard_deviation = sqrt( $y_variance );

	//Calculate the regression gradient
	$m = $x_standard_deviation != 0 && $y_standard_deviation != 0 ? $r * ( $x_standard_deviation / $y_standard_deviation ) : 0;

	//Calculate the regression intercept
	//$c = $y_mean - ( $m * $x_mean );
	//echo "gradient: $m <br />";
	//echo "intercept: $c <br />";
	return $m;
}





//View
#$oValr = new VALR('');
