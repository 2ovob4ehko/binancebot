<?php

namespace App\BinanceSDK;

use Binance\API;

class BinanceSDK extends API
{
    protected $fapiTestnet = 'https://testnet.binancefuture.com/fapi/';
    protected $fapi = 'https://fapi.binance.com/fapi/';
    protected $streamFTestnet = 'wss://stream.binancefuture.com/ws/';
    protected $streamF = 'wss://fstream.binance.com/ws/';
    protected $exchangeInfoFuture = null;

//    TODO: Add Binance functions for FUTURES orders
// https://binance-docs.github.io/apidocs/futures/en/#query-order-user_data

    public function cancelFuture(string $symbol, $orderid, $flags = [])
    {
        $params = [
            'fapi' => true,
            "symbol" => $symbol,
            "orderId" => $orderid,
        ];
        return $this->httpRequest("v1/order", "DELETE", array_merge($params, $flags), true);
    }

    /**
     * @param string $symbol
     * @param $orderid
     * @return array|mixed
     * @throws \Exception
     */
    public function orderFutureStatus(string $symbol, $orderid)
    {
        return $this->httpRequest("v1/order", "GET", [
            'fapi' => true,
            "symbol" => $symbol,
            "orderId" => $orderid,
        ], true);
    }
    /**
     * @return array|mixed
     * @throws \Exception
     */
    public function exchangeInfoFuture()
    {
        if (!$this->exchangeInfoFuture) {
            $arr = $this->httpRequest("v1/exchangeInfo",'GET', [ 'fapi' => true ]);

            $this->exchangeInfoFuture = $arr;
            $this->exchangeInfoFuture['symbols'] = null;

            foreach ($arr['symbols'] as $key => $value) {
                $this->exchangeInfoFuture['symbols'][$value['symbol']] = $value;
            }
        }

        return $this->exchangeInfoFuture;
    }

    /**
     * @return array|mixed
     * @throws \Exception
     */
    public function accountFuture()
    {
        $result = $this->httpRequest("v2/account",'GET', [ 'fapi' => true ], true);
        $output = $result;
        $output['positions'] = null;

        foreach ($result['positions'] as $key => $value) {
            $output['positions'][$value['symbol']] = $value;
        }

        return $output;
    }

    /**
     * @param string $symbol
     * @param int $leverage 1- 125
     * @return array|mixed
     * @throws \Exception
     */
    public function leverageFuture(string $symbol, int $leverage)
    {
        $params = [
            'fapi' => true,
            'symbol' => $symbol,
            'leverage' => $leverage
        ];

        return $this->httpRequest("v1/leverage",'POST', $params, true);
    }

    /**
     * @param string $symbol
     * @param bool $isIsolated
     * @return array|mixed
     * @throws \Exception
     */
    public function marginTypeFuture(string $symbol, bool $isIsolated)
    {
        $params = [
            'fapi' => true,
            'symbol' => $symbol,
            'marginType' => $isIsolated ? 'ISOLATED' : 'CROSSED'
        ];

        return $this->httpRequest("v1/marginType",'POST', $params, true);
    }

    public function buyFuture(string $symbol, string $positionSide, $quantity, $price, string $type = "LIMIT", array $flags = [])
    {
        return $this->orderFuture("BUY", $symbol, $positionSide, $quantity, $price, $type, $flags);
    }

    public function sellFuture(string $symbol, string $positionSide, $quantity, $price, string $type = "LIMIT", array $flags = [])
    {
        return $this->orderFuture("SELL", $symbol, $positionSide, $quantity, $price, $type, $flags);
    }

    public function buyMarketFuture(string $symbol, string $positionSide, $quantity, array $flags = [])
    {
        return $this->orderFuture("BUY", $symbol, $positionSide, $quantity, 0, "MARKET", $flags);
    }

    public function sellMarketFuture(string $symbol, string $positionSide, $quantity, array $flags = [])
    {
        $c = $this->numberOfDecimals($this->exchangeInfo()['symbols'][$symbol]['filters'][2]['minQty']);
        $quantity = $this->floorDecimal($quantity, $c);

        return $this->orderFuture("SELL", $symbol, $positionSide, $quantity, 0, "MARKET", $flags);
    }

    public function buyMarketQuoteFuture(string $symbol, string $positionSide, $quantity, array $flags = [])
    {
        $flags['isQuoteOrder'] = true;

        return $this->orderFuture("BUY", $symbol, $positionSide, $quantity, 0, "MARKET", $flags);
    }

    public function sellMarketQuoteFuture(string $symbol, string $positionSide, $quantity, array $flags = [])
    {
        $flags['isQuoteOrder'] = true;
        $c = $this->numberOfDecimals($this->exchangeInfo()['symbols'][$symbol]['filters'][2]['minQty']);
        $quantity = $this->floorDecimal($quantity, $c);

        return $this->orderFuture("SELL", $symbol, $positionSide, $quantity, 0, "MARKET", $flags);
    }

    /**
     * @param string $side BUY, SELL
     * @param string $symbol
     * @param string $positionSide LONG, SHORT
     * @param $quantity
     * @param $price
     * @param string $type
     * @param array $flags
     * @return array
     * @throws \Exception
     */
    public function orderFuture(string $side, string $symbol, string $positionSide, $quantity, $price, string $type = "LIMIT", array $flags = [])
    {
        $opt = [
            "fapi" => true,
            "symbol" => $symbol,
            "side" => $side,
            "positionSide" => $positionSide,
            "type" => $type,
            "quantity" => $quantity,
            "recvWindow" => 60000,
        ];

        // someone has preformated there 8 decimal point double already
        // dont do anything, leave them do whatever they want
        if (gettype($price) !== "string") {
            // for every other type, lets format it appropriately
            $price = number_format($price, 8, '.', '');
        }

        if (is_numeric($quantity) === false) {
            // WPCS: XSS OK.
            echo "warning: quantity expected numeric got " . gettype($quantity) . PHP_EOL;
        }

        if (is_string($price) === false) {
            // WPCS: XSS OK.
            echo "warning: price expected string got " . gettype($price) . PHP_EOL;
        }

        if ($type === "LIMIT" || $type === "STOP_LOSS_LIMIT" || $type === "TAKE_PROFIT_LIMIT") {
            $opt["price"] = $price;
            $opt["timeInForce"] = "GTC";
        }

        if ($type === "MARKET" && isset($flags['isQuoteOrder']) && $flags['isQuoteOrder']) {
            unset($opt['quantity']);
            $opt['quoteOrderQty'] = $quantity;
        }

        if (isset($flags['stopPrice'])) {
            $opt['stopPrice'] = $flags['stopPrice'];
        }

        if (isset($flags['icebergQty'])) {
            $opt['icebergQty'] = $flags['icebergQty'];
        }

        if (isset($flags['newOrderRespType'])) {
            $opt['newOrderRespType'] = $flags['newOrderRespType'];
        }

        $qstring = "v1/order";
        return $this->httpRequest($qstring, "POST", $opt, true);
    }

    /**
     * @param string $isIsolated 'TRUE' 'FALSE'
     * @param string $symbol
     * @param $quantity
     * @param $price
     * @param string $type
     * @param array $flags
     * @return array
     */
    public function marginBuy(string $isIsolated, string $symbol, $quantity, $price, string $type = "LIMIT", array $flags = [])
    {
        return $this->marginOrder("BUY", $isIsolated, $symbol, $quantity, $price, $type, $flags);
    }

    /**
     * @param string $isIsolated
     * @param string $symbol
     * @param $quantity
     * @param $price
     * @param string $type
     * @param array $flags
     * @return array
     */
    public function marginSell(string $isIsolated, string $symbol, $quantity, $price, string $type = "LIMIT", array $flags = [])
    {
        return $this->marginOrder("SELL", $isIsolated, $symbol, $quantity, $price, $type, $flags);
    }

    /**
     * @param string $isIsolated
     * @param string $symbol
     * @param $quantity
     * @param array $flags
     * @return array
     */
    public function marginMarketQuoteBuy(string $isIsolated, string $symbol, $quantity, array $flags = [])
    {
        $flags['isQuoteOrder'] = true;

        return $this->marginOrder("BUY", $isIsolated, $symbol, $quantity, 0, "MARKET", $flags);
    }

    /**
     * @param string $isIsolated
     * @param string $symbol
     * @param $quantity
     * @param array $flags
     * @return array
     */
    public function marginMarketBuy(string $isIsolated, string $symbol, $quantity, array $flags = [])
    {
        return $this->marginOrder("BUY", $isIsolated, $symbol, $quantity, 0, "MARKET", $flags);
    }

    /**
     * @param string $isIsolated
     * @param string $symbol
     * @param $quantity
     * @param array $flags
     * @return array
     * @throws \Exception
     */
    public function marginMarketQuoteSell(string $isIsolated, string $symbol, $quantity, array $flags = [])
    {
        $flags['isQuoteOrder'] = true;
        $c = $this->numberOfDecimals($this->exchangeInfo()['symbols'][$symbol]['filters'][2]['minQty']);
        $quantity = $this->floorDecimal($quantity, $c);

        return $this->marginOrder("SELL", $isIsolated, $symbol, $quantity, 0, "MARKET", $flags);
    }

    /**
     * @param string $isIsolated
     * @param string $symbol
     * @param $quantity
     * @param array $flags
     * @return array
     * @throws \Exception
     */
    public function marginMarketSell(string $isIsolated, string $symbol, $quantity, array $flags = [])
    {
        $c = $this->numberOfDecimals($this->exchangeInfo()['symbols'][$symbol]['filters'][2]['minQty']);
        $quantity = $this->floorDecimal($quantity, $c);

        return $this->marginOrder("SELL", $isIsolated, $symbol, $quantity, 0, "MARKET", $flags);
    }

    /**
     * @param string $side
     * @param string $isIsolated
     * @param string $symbol
     * @param $quantity
     * @param $price
     * @param string $type
     * @param array $flags
     * @return array
     * @throws \Exception
     */
    public function marginOrder(string $side, string $isIsolated, string $symbol, $quantity, $price, string $type = "LIMIT", array $flags = [])
    {
        $opt = [
            "sapi" => true,
            "symbol" => $symbol,
            "isIsolated" => $isIsolated,
            "side" => $side,
            "type" => $type,
            "quantity" => $quantity,
            "recvWindow" => 60000,
        ];

        // someone has preformated there 8 decimal point double already
        // dont do anything, leave them do whatever they want
        if (gettype($price) !== "string") {
            // for every other type, lets format it appropriately
            $price = number_format($price, 8, '.', '');
        }

        if (is_numeric($quantity) === false) {
            // WPCS: XSS OK.
            echo "warning: quantity expected numeric got " . gettype($quantity) . PHP_EOL;
        }

        if (is_string($price) === false) {
            // WPCS: XSS OK.
            echo "warning: price expected string got " . gettype($price) . PHP_EOL;
        }

        if ($type === "LIMIT" || $type === "STOP_LOSS_LIMIT" || $type === "TAKE_PROFIT_LIMIT") {
            $opt["price"] = $price;
            $opt["timeInForce"] = "GTC";
        }

        if ($type === "MARKET" && isset($flags['isQuoteOrder']) && $flags['isQuoteOrder']) {
            unset($opt['quantity']);
            $opt['quoteOrderQty'] = $quantity;
        }

        if (isset($flags['stopPrice'])) {
            $opt['stopPrice'] = $flags['stopPrice'];
        }

        if (isset($flags['icebergQty'])) {
            $opt['icebergQty'] = $flags['icebergQty'];
        }

        if (isset($flags['newOrderRespType'])) {
            $opt['newOrderRespType'] = $flags['newOrderRespType'];
        }

        $qstring = "v1/margin/order";
        return $this->httpRequest($qstring, "POST", $opt, true);
    }

    /**
     * marketSell attempts to create a currency order at given market price
     *
     * $quantity = 1;
     * $order = $api->marketSell("BNBBTC", $quantity);
     *
     * @param $symbol string the currency symbol
     * @param $quantity string the quantity required
     * @param $flags array addtional options for order type
     * @return array with error message or the order details
     */
    public function marketSell(string $symbol, $quantity, array $flags = [])
    {
        $c = $this->numberOfDecimals($this->exchangeInfo()['symbols'][$symbol]['filters'][1]['minQty']);
        $quantity = $this->floorDecimal($quantity, $c);

        return $this->order("SELL", $symbol, $quantity, 0, "MARKET", $flags);
    }

    protected function httpRequest(string $url, string $method = "GET", array $params = [], bool $signed = false)
    {
        if (function_exists('curl_init') === false) {
            throw new \Exception("Sorry cURL is not installed!");
        }

        if ($this->caOverride === false) {
            if (file_exists(getcwd() . '/ca.pem') === false) {
                $this->downloadCurlCaBundle();
            }
        }

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_VERBOSE, $this->httpDebug);
        $query = http_build_query($params, '', '&');

        $base = $this->getRestEndpoint();
        if (isset($params['wapi'])) {
            if ($this->useTestnet) {
                throw new \Exception("wapi endpoints are not available in testnet");
            }
            unset($params['wapi']);
            $base = $this->wapi;
        }

        if (isset($params['sapi'])) {
            if ($this->useTestnet) {
                throw new \Exception("sapi endpoints are not available in testnet");
            }
            unset($params['sapi']);
            $base = $this->sapi;
        }

        if (isset($params['fapi'])) {
            if ($this->useTestnet) {
                $base = $this->fapiTestnet;
            }else{
                $base = $this->fapi;
            }
            unset($params['fapi']);
        }
        // signed with params
        if ($signed === true) {
            if (empty($this->api_key)) {
                throw new \Exception("signedRequest error: API Key not set!");
            }

            if (empty($this->api_secret)) {
                throw new \Exception("signedRequest error: API Secret not set!");
            }

            $ts = (microtime(true) * 1000) + $this->info['timeOffset'];
            $params['timestamp'] = number_format($ts, 0, '.', '');

            $query = http_build_query($params, '', '&');
            $query = str_replace([ '%40' ], [ '@' ], $query);//if send data type "e-mail" then binance return: [Signature for this request is not valid.]
            $signature = hash_hmac('sha256', $query, $this->api_secret);
            if ($method === "POST") {
                $endpoint = $base . $url;
                $params['signature'] = $signature; // signature needs to be inside BODY
                $query = http_build_query($params, '', '&'); // rebuilding query
            } else {
                $endpoint = $base . $url . '?' . $query . '&signature=' . $signature;
            }

            curl_setopt($curl, CURLOPT_URL, $endpoint);
            curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                'X-MBX-APIKEY: ' . $this->api_key,
            ));
        }
        // params so buildquery string and append to url
        elseif (count($params) > 0) {
            curl_setopt($curl, CURLOPT_URL, $base . $url . '?' . $query);
        }
        // no params so just the base url
        else {
            curl_setopt($curl, CURLOPT_URL, $base . $url);
            curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                'X-MBX-APIKEY: ' . $this->api_key,
            ));
        }
        curl_setopt($curl, CURLOPT_USERAGENT, "User-Agent: Mozilla/4.0 (compatible; PHP Binance API)");
        // Post and postfields
        if ($method === "POST") {
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $query);
        }
        // Delete Method
        if ($method === "DELETE") {
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        }

        // PUT Method
        if ($method === "PUT") {
            curl_setopt($curl, CURLOPT_PUT, true);
        }

        // proxy settings
        if (is_array($this->proxyConf)) {
            curl_setopt($curl, CURLOPT_PROXY, $this->getProxyUriString());
            if (isset($this->proxyConf['user']) && isset($this->proxyConf['pass'])) {
                curl_setopt($curl, CURLOPT_PROXYUSERPWD, $this->proxyConf['user'] . ':' . $this->proxyConf['pass']);
            }
        }
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_HEADER, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 60);

        // set user defined curl opts last for overriding
        foreach ($this->curlOpts as $key => $value) {
            curl_setopt($curl, constant($key), $value);
        }

        if ($this->caOverride === false) {
            if (file_exists(getcwd() . '/ca.pem') === false) {
                $this->downloadCurlCaBundle();
            }
        }

        $output = curl_exec($curl);
        // Check if any error occurred
        if (curl_errno($curl) > 0) {
            // should always output error, not only on httpdebug
            // not outputing errors, hides it from users and ends up with tickets on github
            throw new \Exception('Curl error: ' . curl_error($curl));
        }

        $header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $header = $this->get_headers_from_curl_response($output);
        $output = substr($output, $header_size);

        curl_close($curl);

        $json = json_decode($output, true);

        $this->lastRequest = [
            'url' => $url,
            'base' => $base,
            'method' => $method,
            'params' => $params,
            'header' => $header,
            'json' => $json
        ];

        if (isset($header['x-mbx-used-weight'])) {
            $this->setXMbxUsedWeight($header['x-mbx-used-weight']);
        }

        if (isset($header['x-mbx-used-weight-1m'])) {
            $this->setXMbxUsedWeight1m($header['x-mbx-used-weight-1m']);
        }

        if (isset($json['msg']) && !empty($json['msg'])) {
            if ( $url != 'v1/system/status' && $url != 'v3/systemStatus.html' && $url != 'v3/accountStatus.html') {
                // should always output error, not only on httpdebug
                // not outputing errors, hides it from users and ends up with tickets on github
                throw new \Exception('signedRequest error: '.print_r($output, true));
            }
        }
        $this->transfered += strlen($output);
        $this->requestCount++;
        return $json;
    }

    private function getRestEndpoint() : string
    {
        return $this->useTestnet ? $this->baseTestnet : $this->base;
    }

    public function filterPrice(string $symbol, $price)
    {
        $minPrice = $this->exchangeInfo()['symbols'][$symbol]['filters'][0]['minPrice'];
        $c = $this->numberOfDecimals($minPrice);
        return $this->floorDecimal($price, $c);
    }

    public function filterQty(string $symbol, $qty, $price)
    {
        $filters = $this->exchangeInfo()['symbols'][$symbol]['filters'];
        $minQty = $filters[1]['minQty'];
        $minNotional = $filters[2]['minNotional'];
        $c = $this->numberOfDecimals($minQty);
        $qty = $this->floorDecimal($qty, $c);
        $quote = $qty * $price;
        while ($quote < $minNotional) {
            $qty += $minQty;
            $quote = $qty * $price;
        }
        return $qty;
    }

    public function filterQuoteQty(string $symbol, $quiteQty, $price)
    {
        $filters = $this->exchangeInfo()['symbols'][$symbol]['filters'];
        $minPrice = $filters[0]['minPrice'];
        $minQty = $filters[1]['minQty'];
        $minNotional = $filters[2]['minNotional'];

        $qty = $quiteQty / $price;
        $c = $this->numberOfDecimals($minQty);
        $cp = $this->numberOfDecimals($minPrice);
        $qty = $this->floorDecimal($qty, $c);
        $testQuoteQty = $qty * $price;

        while ($testQuoteQty < $minNotional) {
            $quiteQty += $minPrice;
            $quiteQty = ceil($quiteQty * pow(10, $cp)) / pow(10, $cp);
            $qty = $quiteQty / $price;
            $qty = $this->floorDecimal($qty, $c);
            $testQuoteQty = $qty * $price;
        }

        return $quiteQty;
    }
}
