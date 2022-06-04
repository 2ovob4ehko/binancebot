<?php

namespace App\Helpers;

class Analysis
{
    /**
     * @param $data
     * @param $period
     * @return array
     */
    public function rsi($data, $period): array
    {
        $up = [];
        $down = [];
        $avgUp = [];
        $avgDown = [];
        $relative = [];
        $rsi = [];

        for ($i=1;$i<count($data);$i++){
            $up[$i] = floatval($data[$i]) > floatval($data[$i-1]) ? floatval($data[$i]) - floatval($data[$i-1]) : 0;
            $down[$i] = floatval($data[$i]) < floatval($data[$i-1]) ? floatval($data[$i-1]) - floatval($data[$i]) : 0;
        }

        $a = array_slice($up, 0, $period);
        $avgUp[$period] = array_sum($a)/count($a);
        $a = array_slice($down, 0, $period);
        $avgDown[$period] = array_sum($a)/count($a);
        $relative[$period] = $avgDown[$period] ? $avgUp[$period]/$avgDown[$period] : 0;
        $rsi[$period] = floor((100-(100/($relative[$period]+1)))*100)/100;
        if($period){
            for ($i=$period+1;$i<count($data);$i++){
                $avgUp[$i] = ($avgUp[$i-1]*($period-1)+$up[$i])/$period;
                $avgDown[$i] = ($avgDown[$i-1]*($period-1)+$down[$i])/$period;
                $relative[$i] = $avgDown[$i] ? $avgUp[$i]/$avgDown[$i] : 0;
                $rsi[$i] = floor((100-(100/($relative[$i]+1)))*100)/100;
            }
        }
        return $rsi;
    }

    /**
     * @param $rsi
     * @param $rsi_period
     * @param $stoch_period
     * @return array[]
     */
    public function stoch_rsi($rsi, $rsi_period, $stoch_period): array
    {
        $stoch_rsi = [];
        $sma_stoch_rsi = [];
        $max = [];
        $min = [];

        if($rsi && $rsi_period && $stoch_period){
            for ($i=$rsi_period*2-1;$i<=max(array_keys($rsi));$i++){
                $max[$i] = max(array_slice($rsi, $i-($rsi_period*2-1), $rsi_period));
                $min[$i] = min(array_slice($rsi, $i-($rsi_period*2-1), $rsi_period));
                $stoch_rsi[$i] = ($max[$i]-$min[$i]) ? ($rsi[$i]-$min[$i])/($max[$i]-$min[$i])*100 : 0;
            }
            for ($i=$rsi_period*2+$stoch_period-2;$i<=max(array_keys($rsi));$i++) {
                $a = array_slice($stoch_rsi, $i-($rsi_period*2+$stoch_period-2), $stoch_period);
                $sma_stoch_rsi[$i] = array_sum($a) / count($a);
            }
        }

        return [
            'stoch_rsi' => $stoch_rsi,
            'sma_stoch_rsi' => $sma_stoch_rsi
        ];
    }

    /**
     * @param $data
     * @param $period1
     * @param $period2
     * @param $period3
     * @return array[]
     */
    public function macd($data,$period1,$period2,$period3)
    {
        $ema1 = [];
        $ema2 = [];
        $macd = [];
        $serial = [];
        $histogram = [];

        $a = array_slice($data, 0, $period1);
        $ema1[$period1-1] = array_sum($a)/count($a);

        for ($i=$period1; $i < count($data); $i++) {
            $ema1[$i] = (($data[$i]-$ema1[$i-1])*(2/($period1+1)))+$ema1[$i-1];
        }

        $a = array_slice($data, 0, $period2);
        $ema2[$period2-1] = array_sum($a)/count($a);
        $macd[$period2-1] = $ema1[$period2-1]-$ema2[$period2-1];

        for ($i=$period2; $i < count($data); $i++) {
            $ema2[$i] = (($data[$i]-$ema2[$i-1])*(2/($period2+1)))+$ema2[$i-1];
            $macd[$i] = $ema1[$i]-$ema2[$i];
        }

        $a = array_slice($macd, 0, $period3);
        $serial[$period2+$period3-2] = array_sum($a)/count($a);
        $histogram[$period2+$period3-2] = $macd[$period2+$period3-2]-$serial[$period2+$period3-2];

        for ($i=$period2+$period3-2; $i < count($data); $i++) {
            $serial[$i] = (($macd[$i]-$serial[$i-1])*(2/($period3+1)))+$serial[$i-1];
            $histogram[$i] = $macd[$i]-$serial[$i];
        }


        return [
            'macd' => $macd,
            'serial' => $serial,
            'histogram' => $histogram
        ];
    }

// Open time
// Open
// High
// Low
// Close
// Volume
// Close time
// -----------------
// Quote asset volume
// Number of trades
// Taker buy base asset volume
// Taker buy quote asset volume
// Ignore.
    public function fakeData()
    {
        return [[1642723200000,"40680.92000000","41100.00000000","35440.45000000","36445.31000000","88860.89199900",1642809599999,"3405501703.22193692",2092561,"41615.08581900","1595831787.70003189","0"],[1642809600000,"36445.31000000","36835.22000000","34008.00000000","35071.42000000","90471.33896100",1642895999999,"3207531048.03065140",2099978,"42722.27823300","1514027491.32039380","0"],[1642896000000,"35071.42000000","36499.00000000","34601.01000000","36244.55000000","44279.52354000",1642982399999,"1572414639.04287740",1142407,"22841.39451000","811501507.03630610","0"],[1642982400000,"36244.55000000","37550.00000000","32917.17000000","36660.35000000","91904.75321100",1643068799999,"3192041586.10318994",1999352,"46469.27293800","1615890700.84249996","0"],[1643068800000,"36660.35000000","37545.14000000","35701.00000000","36958.32000000","49232.40183000",1643155199999,"1799224214.49973920",1248246,"24988.42876000","913198135.60368310","0"],[1643155200000,"36958.32000000","38919.98000000","36234.63000000","36809.34000000","69830.16036000",1643241599999,"2627491976.69085740",1584468,"35240.31542000","1326474948.46322410","0"],[1643241600000,"36807.24000000","37234.47000000","35507.01000000","37160.10000000","53020.87934000",1643327999999,"1925920128.34366030",1308408,"26750.94594000","971815942.27075550","0"],[1643328000000,"37160.11000000","38000.00000000","36155.01000000","37716.56000000","42154.26956000",1643414399999,"1560203931.22012810",1061101,"21496.47551000","795847847.51234220","0"],[1643414400000,"37716.57000000","38720.74000000","37268.44000000","38166.84000000","26129.49682000",1643500799999,"989652386.99664940",816601,"13292.42156000","503624477.65945300","0"],[1643500800000,"38166.83000000","38359.26000000","37351.63000000","37881.76000000","21430.66527000",1643587199999,"812750216.60103190",698593,"10798.38460000","409620744.01694440","0"],[1643587200000,"37881.75000000","38744.00000000","36632.61000000","38466.90000000","36855.24580000",1643673599999,"1386754529.02954460",936014,"18422.26954000","693360023.33165190","0"],[1643673600000,"38466.90000000","39265.20000000","38000.00000000","38694.59000000","34574.44663000",1643759999999,"1334266166.70999890",917617,"17730.99304000","684446571.17936590","0"],[1643760000000,"38694.59000000","38855.92000000","36586.95000000","36896.36000000","35794.68130000",1643846399999,"1353093948.97760130",907905,"17251.43687000","652533107.69991750","0"],[1643846400000,"36896.37000000","37387.00000000","36250.00000000","37311.61000000","32081.10999000",1643932799999,"1179502838.65662640",848961,"16468.10233000","605530493.58165880","0"],[1643932800000,"37311.98000000","41772.33000000","37026.73000000","41574.25000000","64703.95874000",1644019199999,"2526572562.05828510",1380724,"33053.66668000","1291953441.55899960","0"],[1644019200000,"41571.70000000","41913.69000000","40843.01000000","41382.59000000","32532.34372000",1644105599999,"1349805515.93003650",1006352,"16566.08602000","687352854.82541980","0"],[1644105600000,"41382.60000000","42656.00000000","41116.56000000","42380.87000000","22405.16704000",1644191999999,"934168802.88011520",788291,"11546.39088000","481652514.37183210","0"],[1644192000000,"42380.87000000","44500.50000000","41645.85000000","43839.99000000","51060.62006000",1644278399999,"2204523609.90339610",1473274,"25457.79986000","1099936959.03964570","0"],[1644278400000,"43839.99000000","45492.00000000","42666.00000000","44042.99000000","64880.29387000",1644364799999,"2847485971.49773380",1653350,"33004.09337000","1448178622.46600310","0"],[1644364800000,"44043.00000000","44799.00000000","43117.92000000","44372.72000000","34428.16729000",1644451199999,"1512500139.14309950",1066395,"17115.60108000","752210712.41184210","0"],[1644451200000,"44372.71000000","45821.00000000","43174.01000000","43495.44000000","62357.29091000",1644537599999,"2772188373.22278300",1541483,"30323.12245000","1348871895.86772030","0"],[1644537600000,"43495.44000000","43920.00000000","41938.51000000","42373.73000000","44975.16870000",1644623999999,"1937866768.50595330",1169354,"22063.76915000","950913914.02964280","0"],[1644624000000,"42373.73000000","43079.49000000","41688.88000000","42217.87000000","26556.85681000",1644710399999,"1123136004.72372450",830322,"12814.60322000","542061655.42919370","0"],[1644710400000,"42217.87000000","42760.00000000","41870.00000000","42053.66000000","17732.08113000",1644796799999,"750533311.65295650",624898,"8333.13302000","352741586.05972970","0"],[1644796800000,"42053.65000000","42842.40000000","41550.56000000","42535.94000000","34010.13060000",1644883199999,"1436927831.44458620",867622,"16912.76672000","714598651.16597500","0"],[1644883200000,"42535.94000000","44751.40000000","42427.03000000","44544.86000000","38095.19576000",1644969599999,"1672855933.62565900",990694,"19180.32434000","842363401.21284530","0"],[1644969600000,"44544.85000000","44549.97000000","43307.00000000","43873.56000000","28471.87270000",1645055999999,"1250973502.30184530",823745,"14106.69537000","619773473.01658720","0"],[1645056000000,"43873.56000000","44164.71000000","40073.21000000","40515.70000000","47245.99494000",1645142399999,"1993400162.94806680",1067658,"21468.60602000","906038209.78185840","0"],[1645142400000,"40515.71000000","40959.88000000","39450.00000000","39974.44000000","43845.92241000",1645228799999,"1765622365.95275610",1025882,"20609.06764000","829953072.41395250","0"],[1645228800000,"39974.45000000","40444.32000000","39639.03000000","40079.17000000","18042.05510000",1645315199999,"721960636.06466920",586653,"8374.97500000","335209843.63180300","0"],[1645315200000,"40079.17000000","40125.44000000","38000.00000000","38386.89000000","33439.29011000",1645401599999,"1293216601.45187960",811235,"15338.25392000","592897166.44915830","0"],[1645401600000,"38386.89000000","39494.35000000","36800.00000000","37008.16000000","62347.68496000",1645487999999,"2382027320.86176070",1352290,"30617.13558000","1170272316.27587730","0"],[1645488000000,"37008.16000000","38429.00000000","36350.00000000","38230.33000000","53785.94589000",1645574399999,"2010628230.75491760",1212527,"27322.73575000","1021948126.92530640","0"],[1645574400000,"38230.33000000","39249.93000000","37036.79000000","37250.01000000","43560.73200000",1645660799999,"1665486687.23993710",1030872,"21449.95580000","820639942.03183170","0"],[1645660800000,"37250.02000000","39843.00000000","34322.28000000","38327.21000000","120476.29458000",1645747199999,"4335107392.00948810",2622780,"60103.13140000","2164376869.72610720","0"],[1645747200000,"38328.68000000","39683.53000000","38014.37000000","39219.17000000","56574.57125000",1645833599999,"2197889580.28266900",1408558,"29904.01589000","1161436657.82962850","0"],[1645833600000,"39219.16000000","40348.45000000","38573.18000000","39116.72000000","29361.25680000",1645919999999,"1152621719.69438010",924257,"14658.93596000","575852237.98081680","0"],[1645920000000,"39116.73000000","39855.70000000","37000.00000000","37699.07000000","46229.44719000",1646006399999,"1780512702.88640200",1265753,"21789.25080000","840141690.32935450","0"],[1646006400000,"37699.08000000","44225.84000000","37450.17000000","43160.00000000","73945.63858000",1646092799999,"2975160226.16898520",1931087,"38543.78078000","1553136662.00345820","0"],[1646092800000,"43160.00000000","44949.00000000","42809.98000000","44421.20000000","61743.09873000",1646179199999,"2701611023.69431990",1866871,"30937.54085000","1353520463.00847440","0"],[1646179200000,"44421.20000000","45400.00000000","43334.09000000","43892.98000000","57782.65081000",1646265599999,"2550792948.28862420",1653131,"28624.11310000","1264172354.04562620","0"],[1646265600000,"43892.99000000","44101.12000000","41832.28000000","42454.00000000","50940.61021000",1646351999999,"2192152097.92518790",1289320,"24438.40113000","1051969942.21123210","0"],[1646352000000,"42454.00000000","42527.30000000","38550.00000000","39148.66000000","61964.68498000",1646438399999,"2527001270.71946910",1465066,"29322.62323000","1196441375.29618270","0"],[1646438400000,"39148.65000000","39613.24000000","38407.59000000","39397.96000000","30363.13341000",1646524799999,"1187689935.08799350",879727,"14846.61434000","580820200.94256520","0"],[1646524800000,"39397.97000000","39693.87000000","38088.57000000","38420.81000000","39677.26158000",1646611199999,"1542258009.62921880",986352,"19055.16367000","740741244.89651720","0"],[1646611200000,"38420.80000000","39547.57000000","37155.00000000","37988.00000000","63941.20316000",1646697599999,"2447310342.46661930",1454770,"31624.08426000","1210428347.72461300","0"],[1646697600000,"37988.01000000","39362.08000000","37867.65000000","38730.63000000","55528.43367000",1646783999999,"2147541837.68824710",1269742,"27931.99999000","1080277222.53459880","0"],[1646784000000,"38730.63000000","42594.06000000","38656.45000000","41941.71000000","67392.58799000",1646870399999,"2797240840.96074690",1570230,"34598.34753000","1435303843.98738530","0"],[1646870400000,"41941.70000000","42039.63000000","38539.73000000","39422.00000000","71950.25677000",1646956799999,"2853792081.10770610",1512384,"33795.03278000","1339863548.33540330","0"],[1646956800000,"39422.01000000","40236.26000000","38223.60000000","38729.57000000","59018.76420000",1647043199999,"2305825015.60108560",1252571,"29148.02045000","1139093538.35246420","0"],[1647043200000,"38729.57000000","39486.71000000","38660.52000000","38807.36000000","24034.36432000",1647129599999,"939663387.31498930",671733,"11869.94851000","464113572.60856310","0"],[1647129600000,"38807.35000000","39310.00000000","37578.51000000","37777.34000000","32791.82359000",1647215999999,"1268441605.18668750",841844,"15150.32067000","586406176.60099370","0"],[1647216000000,"37777.35000000","39947.12000000","37555.00000000","39671.37000000","46945.45375000",1647302399999,"1821922588.44720660",1114899,"24146.76237000","937313460.62628630","0"],[1647302400000,"39671.37000000","39887.61000000","38098.33000000","39280.33000000","46015.54926000",1647388799999,"1796571984.45849700",1060517,"22657.90532000","885084067.98573150","0"],[1647388800000,"39280.33000000","41718.00000000","38828.48000000","41114.00000000","88120.76167000",1647475199999,"3552563766.36553840",2051837,"44119.65664000","1779529342.31891760","0"],[1647475200000,"41114.01000000","41478.82000000","40500.00000000","40917.90000000","37189.38087000",1647561599999,"1521083189.71086230",935784,"18580.98075000","760015585.80298390","0"],[1647561600000,"40917.89000000","42325.02000000","40135.04000000","41757.51000000","45408.00969000",1647647999999,"1862337033.48472110",1065054,"22374.92757000","918115615.04648450","0"],[1647648000000,"41757.51000000","42400.00000000","41499.29000000","42201.13000000","29067.18108000",1647734399999,"1216709156.44280540",801197,"14463.06148000","605480703.88597740","0"],[1647734400000,"42201.13000000","42296.26000000","40911.00000000","41262.11000000","30653.33468000",1647820799999,"1273880705.25024960",799695,"14636.72662000","608328201.79066880","0"],[1647820800000,"41262.11000000","41544.22000000","40467.94000000","41002.25000000","39426.24877000",1647907199999,"1618789548.06842130",947206,"19079.42652000","783366152.12001520","0"],[1647907200000,"41002.26000000","43361.00000000","40875.51000000","42364.13000000","59454.94294000",1647993599999,"2525975507.80119990",1367343,"30215.54785000","1283334795.32526190","0"],[1647993600000,"42364.13000000","43025.96000000","41751.47000000","42882.76000000","40828.87039000",1648079999999,"1725787170.58239640",916671,"20211.34116000","854479820.53341520","0"],[1648080000000,"42882.76000000","44220.89000000","42560.46000000","43991.46000000","56195.12374000",1648166399999,"2436258223.62233810",1202541,"27963.10189000","1212354441.13958370","0"],[1648166400000,"43991.46000000","45094.14000000","43579.00000000","44313.16000000","54614.43648000",1648252799999,"2421153187.66041230",1141588,"27819.34765000","1233475690.36364220","0"],[1648252800000,"44313.16000000","44792.99000000","44071.97000000","44511.27000000","23041.61741000",1648339199999,"1022802857.60941000",693647,"11422.59021000","507079959.20991110","0"],[1648339200000,"44511.27000000","46999.00000000","44421.46000000","46827.76000000","41874.91071000",1648425599999,"1907019662.34414990",1135096,"21674.84642000","987241345.64629310","0"],[1648425600000,"46827.76000000","48189.84000000","46663.56000000","47122.21000000","58949.26140000",1648511999999,"2789898402.61657610",1498975,"29097.75719000","1377280753.97272760","0"],[1648512000000,"47122.21000000","48096.47000000","46950.85000000","47434.80000000","36772.28457000",1648598399999,"1748600385.45023520",1095678,"18192.37420000","865179145.17564560","0"],[1648598400000,"47434.79000000","47700.22000000","46445.42000000","47067.99000000","40947.20850000",1648684799999,"1932152559.88311230",1081607,"20896.43399000","985946938.56827850","0"],[1648684800000,"47067.99000000","47600.00000000","45200.00000000","45510.34000000","48645.12667000",1648771199999,"2262485160.56858420",1290541,"22879.47328000","1064278670.64261090","0"],[1648771200000,"45510.35000000","46720.09000000","44200.00000000","46283.49000000","56271.06474000",1648857599999,"2556532890.87031090",1372216,"28041.37621000","1274359931.84053690","0"],[1648857600000,"46283.49000000","47213.00000000","45620.00000000","45811.00000000","37073.53582000",1648943999999,"1721690754.43939360",1056716,"18562.93079000","862197811.56889240","0"],[1648944000000,"45810.99000000","47444.11000000","45530.92000000","46407.35000000","33394.67794000",1649030399999,"1550999693.05080480",966563,"16740.18067000","777615974.81551770","0"],[1649030400000,"46407.36000000","46890.71000000","45118.00000000","46580.51000000","44641.87514000",1649116799999,"2053434523.86853890",1171998,"21913.04154000","1008164276.04901840","0"],[1649116800000,"46580.50000000","47200.00000000","45353.81000000","45497.55000000","42192.74852000",1649203199999,"1951668192.92950980",1046080,"20607.57735000","953494654.45992840","0"],[1649203200000,"45497.54000000","45507.14000000","43121.00000000","43170.47000000","60849.32936000",1649289599999,"2700644700.89039180",1471912,"29467.28381000","1307725131.19462540","0"],[1649289600000,"43170.47000000","43900.99000000","42727.35000000","43444.19000000","37396.54156000",1649375999999,"1623879486.66003130",999816,"18567.25999000","806358703.21566620","0"],[1649376000000,"43444.20000000","43970.62000000","42107.14000000","42252.01000000","42375.04203000",1649462399999,"1830699971.61131170",1108136,"20530.56354000","887196762.49432090","0"],[1649462400000,"42252.02000000","42800.00000000","42125.48000000","42753.97000000","17891.66047000",1649548799999,"759274586.61377760",640230,"8910.47179000","378158044.30397130","0"],[1649548800000,"42753.96000000","43410.30000000","41868.00000000","42158.85000000","22771.09403000",1649635199999,"971459737.50094040",678983,"10952.24952000","467438845.42218350","0"],[1649635200000,"42158.85000000","42414.71000000","39200.00000000","39530.45000000","63560.44721000",1649721599999,"2602039073.01504450",1385924,"30543.05561000","1251713135.50000790","0"],[1649721600000,"39530.45000000","40699.00000000","39254.63000000","40074.94000000","57751.01778000",1649807999999,"2309514071.59387300",1153667,"28344.99788000","1133659106.65571090","0"],[1649808000000,"40074.95000000","41561.31000000","39588.54000000","41147.79000000","41342.27254000",1649894399999,"1677171454.09107910",948061,"20522.54699000","832653378.51149360","0"],[1649894400000,"41147.78000000","41500.00000000","39551.94000000","39942.38000000","36807.01401000",1649980799999,"1494726286.01801020",803756,"17787.82129000","722216571.85938900","0"],[1649980800000,"39942.37000000","40870.36000000","39766.40000000","40551.90000000","24026.35739000",1650067199999,"966888436.81625090",616536,"11755.54439000","473100437.47337530","0"],[1650067200000,"40551.90000000","40709.35000000","39991.55000000","40378.71000000","15805.44718000",1650153599999,"638275503.91410850",423446,"7642.38243000","308642382.87963840","0"],[1650153600000,"40378.70000000","40595.67000000","39546.17000000","39678.12000000","19988.49259000",1650239999999,"803414248.98369850",590241,"9578.91533000","385124871.46651260","0"],[1650240000000,"39678.11000000","41116.73000000","38536.51000000","40801.13000000","54243.49575000",1650326399999,"2153574975.91313360",1157741,"27097.19375000","1076513006.70581750","0"],[1650326400000,"40801.13000000","41760.00000000","40571.00000000","41493.18000000","35788.85843000",1650412799999,"1472363265.36218780",934526,"17806.81506000","732539241.88967900","0"],[1650412800000,"41493.19000000","42199.00000000","40820.00000000","41358.19000000","40877.35041000",1650499199999,"1697039284.08530060",1063843,"20007.29623000","830754792.62366230","0"],[1650499200000,"41358.19000000","42976.00000000","39751.00000000","40480.01000000","59316.27657000",1650585599999,"2476061417.88725770",1515456,"29413.89548000","1228429890.95735430","0"],[1650585600000,"40480.01000000","40795.06000000","39177.00000000","39709.18000000","46664.01960000",1650671999999,"1870172362.40225000",1043471,"23248.71242000","931654706.55319750","0"],[1650672000000,"39709.19000000","39980.00000000","39285.00000000","39441.60000000","20291.42375000",1650758399999,"804677022.90789710",724132,"10217.20484000","405193110.94368160","0"],[1650758400000,"39441.61000000","39940.00000000","38929.62000000","39450.13000000","26703.61186000",1650844799999,"1056336243.01986090",868183,"12639.70229000","500129939.07795730","0"],[1650844800000,"39450.12000000","40616.00000000","38200.00000000","40426.08000000","63037.12784000",1650931199999,"2470071005.24014250",1280351,"31958.39557000","1252785476.60286050","0"],[1650931200000,"40426.08000000","40797.31000000","37702.26000000","38112.65000000","66650.25800000",1651017599999,"2615206861.83491330",1381618,"31936.94220000","1253685872.44457550","0"],[1651017600000,"38112.64000000","39474.72000000","37881.31000000","39235.72000000","57083.12272000",1651103999999,"2218523642.42638700",1232083,"28942.08915000","1124748467.70036320","0"],[1651104000000,"39235.72000000","40372.63000000","38881.43000000","39742.07000000","56086.67150000",1651190399999,"2223808385.80637570",1135242,"28519.46629000","1130865428.79361840","0"],[1651190400000,"39742.06000000","39925.25000000","38175.00000000","38596.11000000","51453.65715000",1651276799999,"2006135662.85599920",1101140,"24773.60543000","966125835.49613280","0"],[1651276800000,"38596.11000000","38795.38000000","38493.52000000","38583.36000000","9985.34054000",1651363199999,"385866181.28280260",355341,"4955.82820000","191520202.05779620","0"]];
    }
}
