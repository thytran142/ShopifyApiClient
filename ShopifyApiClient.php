<?php

class ShopifyApiClient
{
    private $domain;
    private $token;
    public $shop;
    public $customer;
    public $webhook;
    public $priceRule;
    public $discountCode;
    public $theme;
    public $asset;
    public $product;

    /**
     * ShopifyApiClient constructor.
     * @param $shop
     * @param $token
     */
    public function __construct($shop, $token)
    {
        $this->setDomain($shop);
        $this->setToken($token);
        $this->registerResources();
    }
    public function setDomain($domain){
        $this->domain = $domain;
    }
    public function getDomain(){
        return $this->domain;
    }
    public function setToken($token){
        $this->token = $token;
    }
    public function getToken(){
        return $this->token;
    }
    public static function getAccessToken($api_key,$shared_secret,$params,$hmac)
    {
        $params = array_diff_key($params, array('hmac' => '')); // Remove hmac from params
        ksort($params); // Sort params lexographically
        $computed_hmac = hash_hmac('sha256', http_build_query($params), $shared_secret);
        if (hash_equals($hmac, $computed_hmac)) {
            Yii::log("Validate correctly at",CLogger::LEVEL_INFO);
            $query = array(
                "client_id" => $api_key, // Your API key
                "client_secret" => $shared_secret, // Your app credentials (secret key)
                "code" => $params['code'] // Grab the access key from the URL
            );
            $access_token_url = "https://" . $params['shop'] . "/admin/oauth/access_token";
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_URL, $access_token_url);
            curl_setopt($ch, CURLOPT_POST, count($query));
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($query));
            $result = curl_exec($ch);
            curl_close($ch);
            // Store the access token
            $result = json_decode($result, true);
            $access_token = $result['access_token'];
            // Show the access token (don't do this in production!)
            return $access_token;
        }else{
            Yii::log("This request is not from Shopify ",CLogger::LEVEL_ERROR);
            return null;
        }
    }
    private function registerResources(){
        $this->shop = new ShopifyApiClientResourceShop($this);
        $this->customer = new ShopifyApiClientResourceCustomer($this);
        $this->webhook = new ShopifyApiClientResourceWebhook($this);
        $this->priceRule = new ShopifyApiClientResourcePriceRule($this);
        $this->discountCode = new ShopifyApiClientResourceDiscountCode($this);
        $this->theme = new ShopifyApiClientResourceTheme($this);
        $this->asset = new ShopifyApiClientResourceAsset($this);
        $this->product = new ShopifyApiClientResourceProduct($this);
        $this->variant = new ShopifyApiClientResourceVariant($this);
    }

    /**
     * @param $endPoint
     * @param $method
     * @param null $payload
     * @throws ShopifyApiException
     */
    public function sendRequest($endPoint,$payload = null,$method){
        $url = "https://" . $this->getDomain() . $endPoint;
        if (!is_null($this->getToken())) $request_headers[] = "X-Shopify-Access-Token: " . $this->getToken();
        $request_headers[] = "Content-Type: application/json";
        if($method == "POST" || $method == "PUT"){
            if(!$payload || !is_array($payload)){
                throw new ShopifyApiException("Invalid payload",100);
            }
            $curlOptions = array(
                CURLOPT_URL           => $url,
                CURLOPT_CUSTOMREQUEST => strtoupper($method),
                CURLOPT_HTTPHEADER    => $request_headers,
                CURLOPT_POSTFIELDS    => json_encode($payload),
                CURLOPT_RETURNTRANSFER=>true
            );
        }else if($method == "DELETE"){
            $curlOptions = array(
                CURLOPT_URL           => $url,
                CURLOPT_CUSTOMREQUEST => 'DELETE',
                CURLOPT_HTTPHEADER    => $request_headers,
                CURLOPT_RETURNTRANSFER=>true
            );
        }else{
            $curlOptions = array(
                CURLOPT_URL => $url,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_HTTPHEADER    => $request_headers,
                CURLOPT_RETURNTRANSFER=>true
            );
        }
        $curlHandle = curl_init();
        curl_setopt_array($curlHandle, $curlOptions);
        $responseBody = curl_exec($curlHandle);
        if (curl_errno($curlHandle))
        {
            $this->handleCurlError($curlHandle);
        }
        $responseBody = json_decode($responseBody, true);
        $responseCode = curl_getinfo($curlHandle, CURLINFO_HTTP_CODE);
        curl_close($curlHandle);
        if ($responseCode < 200 || $responseCode > 299)
        {
            $this->handleResponseError($responseCode, $responseBody);
        }
        return $responseBody;
    }
    /**
     * @param resource $curlHandle
     *
     * @throws ShopifyApiException
     */
    public function handleCurlError($curl){
        $errorMessage = 'Curl error: ' . curl_error($curl);
        throw new ShopifyApiException($errorMessage, curl_errno($curl));
    }
    /**
     * @param int   $responseCode
     * @param array $responseBody
     *
     * @throws ShopifyApiException
     */
    public function handleResponseError($responseCode,$responseBody){
        $errorMessage = 'Unknown error: ' . $responseCode;

        if ($responseBody && array_key_exists('error', $responseBody))
        {
            $errorMessage = $responseBody['error']['message'];
        }
        throw new ShopifyApiException($errorMessage, $responseCode);
    }
    /**
     * @param string $url
     * @param array  $payload
     *
     * @return array
     * @throws ShopifyApiException
     */
    public function create($url,$data){
        return $this->sendRequest($url,$data,"POST");
    }
    /**
     * @param string $url
     * @param array  $payload
     *
     * @return array
     * @throws ShopifyApiException
     */
    public function read($url,$data=array()){
        return $this->sendRequest($url,$data,"GET");
    }
    /**
     * @param string $url
     * @param array  $payload
     *
     * @return array
     * @throws ShopifyApiException
     */
    public function update($url,$data){
        return $this->sendRequest($url,$data,"PUT");
    }
    /**
     * @param string $url
     * @param array  $payload
     *
     * @return array
     * @throws ShopifyApiException
     */
    public function delete($url){
        return $this->sendRequest($url,array(),"DELETE");
    }
}
class ShopifyApiException extends Exception
{
}
class ShopifyApiClientResourceShop{
    private $client;
    public function __construct(ShopifyApiClient $client)
    {
        $this->client = $client;
    }

    /**
     * @return array
     * @throws ShopifyApiException
     */
    public function get(){
        return $this->client->read("/admin/shop.json");
    }
}
class ShopifyApiClientResourceCustomer{
    private $client;
    public function __construct(ShopifyApiClient $client){
        $this->client = $client;
    }

    /**
     * @param $fields
     * @return array
     * @throws ShopifyApiException
     */
    public function create($fields){
        $fields = array('customer'=>$fields);
        return $this->client->create("/admin/customers.json",$fields);
    }

    /**
     * @param null $customerId
     * @param array $params
     * @return array
     * @throws ShopifyApiException
     */
    public function get($customerId = null, $params = array()){
        if($customerId){
            return $this->client->read("/admin/customers/".$customerId.".json",$params);
        }else{
            return $this->client->read("/admin/customers.json",$params);
        }
    }

    /**
     * @param $customerId
     * @param $fields
     * @return array
     * @throws ShopifyApiException
     */
    public function update($customerId, $fields){
        $fields = array('customer'=>$fields);
        return $this->client->update('/admin/customers/'.$customerId.".json",$fields);
    }

    /**
     * @param $customerId
     * @return array
     * @throws ShopifyApiException
     */
    public function delete($customerId){
        return $this->client->delete("/admin/customers/".$customerId.".json");
    }
}
class ShopifyApiClientResourceWebhook{
    private $client;
    public function __construct(ShopifyApiClient $client){
        $this->client = $client;
    }

    /**
     * @param $fields
     * @return array
     * @throws ShopifyApiException
     */
    public function create($fields){
        $fields = array('webhook'=>$fields);
        return $this->client->create('/admin/webhooks.json',$fields);
    }

    /**
     * @param null $id
     * @param array $params
     * @return array
     * @throws ShopifyApiException
     */
    public function get($id = null, $params = array()){
        if($id){
            return $this->client->read("/admin/webhooks/".$id.".json",$params);
        }else{
            return $this->client->read("/admin/webhooks.json",$params);
        }
    }

    /**
     * @param $id
     * @param $fields
     * @return array
     * @throws ShopifyApiException
     */
    public function update($id, $fields){
        $fields = array("webhook"=>$fields);
        return $this->client->update("/admin/webhooks/".$id.".json",$fields);
    }

    /**
     * @param $id
     * @return array
     * @throws ShopifyApiException
     */
    public function delete($id){
        return $this->client->delete("/admin/webhooks/".$id.".json");
    }
}
class ShopifyApiClientResourcePriceRule{
    private $client;
    public function __construct(ShopifyApiClient $client){
        $this->client = $client;
    }

    /**
     * @param $fields
     * @return array
     * @throws ShopifyApiException
     */
    public function create($fields){
        $fields = array("price_rule"=>$fields);
        return $this->client->create("/admin/price_rules.json",$fields);
    }

    /**
     * @param $id
     * @param array $params
     * @return array
     * @throws ShopifyApiException
     */
    public function get($id, $params=array()){
        if($id){
            return $this->client->read("/admin/price_rules/".$id.".json",$params);
        }else{
            return $this->client->read("/admin/price_rules.json",$params);
        }
    }

    /**
     * @param $id
     * @param $fields
     * @return array
     * @throws ShopifyApiException
     */
    public function update($id, $fields){
        $fields = array("price_rule"=>$fields);
        return $this->client->update("/admin/price_rules/".$id.".json",$fields);
    }

    /**
     * @param $id
     * @return array
     * @throws ShopifyApiException
     */
    public function delete($id){
        return $this->client->delete("/admin/webhooks/".$id.".json");
    }
}
class ShopifyApiClientResourceDiscountCode{
    private $client;
    public function __construct(ShopifyApiClient $client){
        $this->client = $client;
    }

    /**
     * @param $price_rule_id
     * @param $fields
     * @return array
     * @throws ShopifyApiException
     */
    public function create($price_rule_id, $fields){
        $fields = array("discount_code"=>$fields);
        return $this->client->create("/admin/price_rules/".$price_rule_id."/discount_codes.json",$fields);
    }

    /**
     * @param $price_rule_id
     * @param $discount_code_id
     * @param $fields
     * @return array
     * @throws ShopifyApiException
     */
    public function update($price_rule_id, $discount_code_id, $fields){
        $fields = array("discount_code"=>$fields);
        return $this->client->update("/admin/price_rules/".$price_rule_id."/discount_codes/".$discount_code_id.".json",$fields);
    }

    /**
     * @param $price_rule_id
     * @param $discount_code_id
     * @return array
     * @throws ShopifyApiException
     */
    public function delete($price_rule_id, $discount_code_id){
        return $this->client->delete("/admin/price_rules/".$price_rule_id."/discount_codes/".$discount_code_id.".json");
    }
}
class ShopifyApiClientResourceTheme{
    private $client;
    public function __construct(ShopifyApiClient $client){
        $this->client = $client;
    }

    /**
     * @param $id
     * @param array $params
     * @return array
     * @throws ShopifyApiException
     */
    public function read($id=null, $params=array()){
        if($id){
            return $this->client->read("/admin/themes/".$id.".json",$params);
        }else{
            return $this->client->read("/admin/themes.json",$params);
        }
    }

    /**
     * @param $fields
     * @return array
     * @throws ShopifyApiException
     */
    public function create($fields){
        $fields = array("theme"=>$fields);
        return $this->client->create("/admin/themes.json",$fields);
    }

    /**
     * @param $id
     * @param $fields
     * @return array
     * @throws ShopifyApiException
     */
    public function update($id, $fields){
        $fields = array("theme"=>$fields);
        return $this->client->update("/admin/themes/".$id.".json",$fields);
    }

    /**
     * @param $id
     * @return array
     * @throws ShopifyApiException
     */
    public function delete($id){
        return $this->client->delete("/admin/themes/".$id.".json");
    }
}
class ShopifyApiClientResourceAsset{
    private $client;
    public function __construct(ShopifyApiClient $client){
        $this->client = $client;
    }

    /**
     * @param $themeId
     * @param $key
     * @param array $params
     * @return array
     * @throws ShopifyApiException
     */
    public function read($themeId, $key, $params=array()){
        if(!$key){
            return $this->client->read("/admin/themes/".$themeId."/assets.json",$params);
        }else{
            return $this->client->read("/admin/themes/".$themeId."/assets.json?asset[key]=".$key."&theme_id=".$themeId);
        }
    }

    /**
     * @param $themeId
     * @param $fields
     * @return array
     * @throws ShopifyApiException
     */
    public function create($themeId, $fields){
        $fields = array("asset"=>$fields);
        return $this->client->update("/admin/themes/".$themeId."/assets.json",$fields);
    }

    /**
     * @param $themeId
     * @param $key
     * @return array
     * @throws ShopifyApiException
     */
    public function delete($themeId, $key){
        return $this->client->delete("/admin/themes/".$themeId."/assets.json?asset[key]=".$key);
    }
}
class ShopifyApiClientResourceVariant{
    private $client;
    public function __construct(ShopifyApiClient $client){
        $this->client = $client;
    }
    /**
     * @param $id
     * @param array $params
     * @return array
     * @throws ShopifyApiException
     */
    public function read($variantId=null, $productId =null, $params=array()){
        if($variantId){
            return $this->client->read("/admin/variants/".$variantId.".json",$params);
        }else if($productId){
            return $this->client->read("/admin/products/".$productId."/variants.json",$params);
        }else{
            return null;//nothing
        }
    }

}
class ShopifyApiClientResourceProduct{
    private $client;
    public function __construct(ShopifyApiClient $client){
        $this->client = $client;
    }

    /**
     * @param $id
     * @param array $params
     * @return array
     * @throws ShopifyApiException
     */
    public function read($id=null, $params=array()){
        if($id){
            return $this->client->read("/admin/products/".$id.".json",$params);
        }else{
            return $this->client->read("/admin/products.json",$params);
        }
    }

    /**
     * @param $fields
     * @return array
     * @throws ShopifyApiException
     */
    public function create($fields){
        $fields = array("product"=>$fields);
        return $this->client->create("/admin/products.json",$fields);
    }

    /**
     * @param $id
     * @param $fields
     * @return array
     * @throws ShopifyApiException
     */
    public function update($id, $fields){
        $fields = array("product"=>$fields);
        return $this->client->update("/admin/products/".$id.".json",$fields);
    }


    /**
     * @param $id
     * @return array
     * @throws ShopifyApiException
     */
    public function delete($id){
        return $this->client->delete("/admin/products/".$id.".json");
    }
}

